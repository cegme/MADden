

## Statistical text processing

### Data Sets
	
	We scraped some statistics data from popolar NFL websites.
	We grabbed some weekend tweets based on football terms.

### Queries

1. Give me 5 comments about a 'Tebow'  with 'negative' sentiment.


We first need a way to perform sentiment analysis from tweets.
Fortunatly, we do not have to do anything new, we can use a popular 
API developed by smart guys at stanford.
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
lets look for tebow. We can simply add a condition in a the where
clause.


    SELECT cgrant_sentiment(twtext) AS sent, twtext, twuser_id_str, id_str 
    FROM tweets
    WHERE twtext iLike '%tebow%'
    LIMIT 5;


If we wanted to do something a little smater. We can look for words that
may contain spelling errors using qgrams. We create another UDF to take
care of the qgram distance calculation. We could alternativley used a package
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


2. Give me all the named entities from an arbitrary block of text.

The easy way to perform this task is to use an easy tool kit such as NLTK.
We can do they same thing using user defined functions.



