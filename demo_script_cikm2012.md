

## Statistical text processing

### Data Sets
	
We scraped some statistics data from popular NFL websites.
We grabbed some weekend tweets based on football terms.

### Queries

#### 1. Give me 5 comments about a 'Tebow'  with 'negative' sentiment.


We first need a way to perform sentiment analysis from tweets.
Fortunately, we do not have to do anything new, we can use a popular 
API developed by smart guys at Stanford.
We can wrap all calls to the api into a UDF.

    CREATE OR REPLACE FUNCTION cgrant_sentiment(t text) RETURNS character AS $$
    import json
    import urllib
    import urllib2
    from urllib2 import urlopen
    
    url = 'http://partners-v1.twittersentiment.appspot.com/api/bulkClassifyJson'
    values = json.dumps({'data':[{'text': unicode(t, errors='ignore') }]}, encoding="utf-8")
    
    req = urllib2.Request(url, values)
    r = urllib2.urlopen(req).read()
    
    j = json.loads(unicode(r, errors='ignore')) # We ignore unicode chars
    val = int(j['data'][0]['polarity'])
    if val == 0:
    	return '-' # Negative
    elif val == 4:
    	return '+' # Positive 
    else:
    	return 'o' # Neutral 
    $$
      LANGUAGE plpythonu IMMUTABLE;



Now we can do ad-hoc sentiment analysis on an text.

    SELECT cgrant_sentiment(twtext) AS sent, twtext, twuser_id_str, id_str 
    FROM tweets
    LIMIT 5;


Now to complete the query we want to search for a particular player,
lets look for Tebow. We can simply add a condition in the where
clause.


    SELECT cgrant_sentiment(twtext) AS sent, twtext, twuser_id_str, id_str 
    FROM tweets
    WHERE twtext iLike '%tebow%'
    LIMIT 5;


If we wanted to do something a little smarter. We can look for words that
may contain spelling errors using qgrams. We create another UDF to take
care of the qgram distance calculation. We could alternatively used a package
that comes with postgres called `pg_similarity`.


    SELECT cgrant_sentiment(twtext) AS sent, twtext, twuser_id_str, id_str 
    FROM tweets 
    WHERE (cgrant_distance(1,'tebow',2,twtext,5) > .5) AND 
    cgrant_sentiment(twtext) = '-' LIMIT 5;


Here is the query plan for this query.


                                                                        QUERY PLAN                                                                     
    ---------------------------------------------------------------------------------------------------------------------------------------------------
     Limit  (cost=0.00..1705.51 rows=5 width=127)
       ->  Seq Scan on tweets  (cost=0.00..13594250.82 rows=39854 width=127)
             Filter: ((cgrant_distance(1::numeric, 'tebow'::text, 2::numeric, twtext, 5::numeric) > 0.5) AND (cgrant_sentiment(twtext) = '-'::bpchar))
    (3 rows)


This query shows a sophisticated query over a complicated query interface.


#### 2. Give me all the named entities from an arbitrary block of text.

The easy way to perform this task is to use an easy tool kit such as NLTK.
We can do they same thing using user defined functions.

Lets start by creating a function to tag a block of text with part of speech.


    CREATE OR REPLACE FUNCTION cgrant_postag(doc text) RETURNS SETOF pair AS $$
    import nltk
    return nltk.pos_tag(nltk.word_tokenize(doc))
    $$
    	LANGUAGE plpythonu VOLATILE;

We first tokenize and then keep the part of speech tag.
Notice we first create a type `pair` that represents output of the function.
This is defined as follows:

    CREATE TYPE pair AS (term text, pos text);

Here is an example run `SELECT cgrant_postag('The Miami Dolphins have a chance to win a superbowl');`.

       term    | pos  
    -----------+------
     The       | DT
     Miami     | NNP
     Dolphins  | NNPS
     have      | VBP
     a         | DT
     chance    | NN
     to        | TO
     win       | VB
     a         | DT
     superbowl | NN
    (10 rows)


With that experience, we can build our function.

    CREATE OR REPLACE FUNCTION cgrant_ne_chunk(doc text, hardtags boolean) RETURNS SETOF netriple AS $$
    import nltk
    from types import TupleType
    from django.utils.encoding import smart_unicode
    seq = 0
    tok = nltk.word_tokenize(smart_unicode(doc, errors='ignore'))
    pos = nltk.pos_tag(tok)
    chunk = nltk.ne_chunk(pos, hardtags)
    array = []
    for res in chunk:
    	if isinstance(res, TupleType):
    		array.append( (seq, res[0], res[1], None))
    		seq += 1
    	else:
    		for x in res.pos():
    			array.append((seq, x[0][0], x[0][1], x[1]))
    		seq += 1
    return array
    $$
      LANGUAGE plpythonu VOLATILE;


First we create another type called netriple which is the return type of the named entity process.
This is actually has for items, but triple sounds better.

    CREATE TYPE netriple (termnum integer, term text, pos text, tag text);

We use the python nltk function `ne_chunk` to do the named entity extraction.
They have classifiers that are pre-trained.
We also added a convenience method so we can either return `NE` for named entity or the type of model.
Here is an example of this function execution.

    SELECT * FROM cgrant_ne_chunk('The Miami Dolphins have a chance to win a superbowl', true);
     termnum |   term    | pos  | tag 
    ---------+-----------+------+-----
           0 | The       | DT   | 
           1 | Miami     | NNP  | NE
           1 | Dolphins  | NNPS | NE
           2 | have      | VBP  | 
           3 | a         | DT   | 
           4 | chance    | NN   | 
           5 | to        | TO   | 
           6 | win       | VB   | 
           7 | a         | DT   | 
           8 | superbowl | NN   | 
    (10 rows)


### CRFs and MADlib introduction
CRFs are the state of art probabilistic models on a number of real-world
tasks including NLP tasks such as POS, NER. We contributed a linear-chain CRF learning and 
inference modules to MADlib which is an open-source library for scalable in-database analytics. 

#### 3. Parallel linear-chain CRF training for part of speech tagging.
We use a Python UDF to drive the computation until the stop criterion is met. Within each
iteration, we use user-deﬁned aggregate functions to parallel the computation
of the log-likelihood and gradient vector over all documents. At the end of
each iteration, the LBFGS optimization is adopted to update the weight vector.

    set search_path=madlib,madlib;
    select crf_train_data('/home/gpadmin/demo/crf/crf_train_data/trainingdataset');
    select crf_train_fgen('train_segmenttbl', 'crf_regex','crf_dictionary', 'featuretbl','crf_feature_dic');
    select lincrf('featuretbl','sparse_r','dense_m','sparse_m','f_size',45, 'crf_feature_dic','crf_feature',20);

#### 4. Parallel TOP1 linear-chain CRF Viterbi inference for part of speech tagging.
The Viterbi algorithm is the popular algorithm to ﬁnd the top-k most likely
labelings of a document for CRF models. We chose to implement a SQL statement
to drive the Viterbi inference. SQL is inherently parallel due to the set operation over relations.
In Greenplum, Viterbi can be run in parallel over different subsets of the document on a multi-core machine.

    select crf_test_data('/home/gpadmin/demo/crf/crf_test_data/testingdataset');
    select crf_test_fgen('test_segmenttbl','crf_dictionary','crf_label','crf_regex',' crf_feature','viterbi_mtbl','viterbi_rtbl');
    select vcrf_label('test_segmenttbl', 'viterbi_mtbl','viterbi_rtbl', 'crf_label', 'extraction');
 

