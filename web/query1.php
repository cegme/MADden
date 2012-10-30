<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<title>MADden: In-Database Text Analytics</title>
		<link rel="stylesheet" type="text/css" href="bootstrap-1.4.0.min.css" />
		<link href="prettify/prettify.css" type="text/css" rel="stylesheet" />
		<script type="text/javascript" src="http://code.jquery.com/jquery-latest.min.js"></script>
    <script language='javascript' src='http://embedtweet.com/javascripts/embed_v2.js'></script>
		<script type="text/javascript" src="prettify/prettify.js"></script>
		<!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]--><style type="text/css">
      body {
        padding-top: 20px;
      }
    </style>
		<script type="text/javascript">
			function slider(e,newValue) { 
				document.getElementById(e).innerHTML=newValue;
			}
			$(document).ready(function() { prettyPrint(); });
		</script>
		<style type="text/css">
      /* Override some defaults */
      html, body {
        background-color: #eee;
      }
      body {
        padding-top: 40px; /* 40px to make the container go all the way to the bottom of the topbar */
      }
      .container > footer p {
        text-align: center; /* center align it with the container */
      }
      .container {
        width: 820px; /* downsize our container to make the content feel a bit tighter and more cohesive. NOTE: this removes two full columns from the grid, meaning you only go to 14 columns and not 16. */
      }

      /* The white background content wrapper */
      .content {
        background-color: #fff;
        padding: 20px;
        margin: 0 -20px; /* negative indent the amount of the padding to maintain the grid system */
        -webkit-border-radius: 0 0 6px 6px;
           -moz-border-radius: 0 0 6px 6px;
                border-radius: 0 0 6px 6px;
        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.15);
           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.15);
                box-shadow: 0 1px 2px rgba(0,0,0,.15);
      }

      /* Page header tweaks */
      .page-header {
        background-color: #f5f5f5;
        padding: 20px 20px 10px;
        margin: -20px -20px 20px;
      }

      /* Styles you shouldn't keep as they are for displaying this base example only */
      .content .span10,
      .content .span4 {
        min-height: 500px;
      }
      /* Give a quick and non-cross-browser friendly divider */
      .content .span4 {
        margin-left: 0;
        padding-left: 19px;
        border-left: 1px solid #eee;
      }

      .topbar .btn {
        border: 0;
      }

    </style>
	</head>

	<body>

		<div class="topbar">
			<div class="fill">
				<div class="container">
					<!-- <a class="brand" href="#">MADden</a> -->
					<ul class="nav">
						<li class="active"><a href="#">Home</a></li>
						<li><a href="http://www.cise.ufl.edu/class/cis6930fa11lad/">cis6930fa11lad</a></li>
						<li><a href="https://github.com/SakuraSound/MADden">The Code</a></li>
					</ul><!-- .nav -->
				</div> <!-- .container -->
			</div> <!-- .fill -->
		</div> <!-- .topbar -->

		<div class="container">
<?php

include 'util.php';

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

// Connecting, selecting database
$_db = make_db_string();
$dbconn = pg_connect("host=".$_db->{'host'}." dbname=".$_db->{'db'}." user=".$_db->{'user'}." password=".$_db->{'pwd'}." options='--client_encoding=UTF8'")
    or die('Could not connect: ' . pg_last_error());

$player = implode("&", explode(" ", $_GET["player"]));
$sent = htmlspecialchars($_GET['sent']);
		// Build query
$query = "select cgrant_sentiment(twtext) as sent, twtext, twuser_id_str, id_str".
	"\nfrom tweets ".
	"\nwhere (cgrant_distance(1,'".$_GET['player']."',2, twtext, 5) > .5) ".
	//"\ntwtextvector @@ to_tsquery('".htmlspecialchars($player)."')) ".
	"\nand cgrant_sentiment(twtext) = '$sent'".
	//"\nGROUP BY sent, text". 
	"\nlimit ".$_GET["num"].";";

		list($tic_usec, $tic_sec) = explode(" ", microtime());
		$result = pg_query($query) or die('Query failed: ' . pg_last_error());
		list($toc_usec, $toc_sec) = explode(" ", microtime());
		$querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec);

		$queryplan = getQueryPlan($dbconn, $query);
?>	

			<div class="content">
        <div class="page-header">
				<h1>John Madden says <?php echo $_GET["q"];?>	
					<small>...</small>
				</h1>
        </div>
        <div class="row">
          <div class="span10">
            <h2>Answer</h2>
						<?php
							// Printing results in HTML
							echo "<table>\n";
							while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
									echo "\t<tr>\n";
									//foreach ($line as $col_value) {
									//		echo "\t\t<td>$col_value</td>\n";
									//}
									echo "\t\t<td>";
									if ($line['sent'] == '+') {
											echo "<div class='alert-message block-message success'".
											">".
											$line['sent'];
									}
									else if ($line['sent'] == '-') {
											echo "<div class='alert-message block-message error'".
											">".
											$line['sent'];
									}
									else {
											echo "<div class='alert-message block-message warning'".
											">".
											$line['sent'];
									}
									//echo "\t\t<td>";
									$twaddr = "https://twitter.com/#!/".
										trim($line['twuser_id_str']).
										"/status/".trim($line['id_str']);
									echo "<a href='$twaddr'>$twaddr</a></div></td>";
									echo "\t</tr>\n";
							}
							echo "</table>\n";
						?>
<h3>Query Plan</h3>
<pre>
<? echo join("\n",$queryplan); ?>
</pre>
          </div>
          <div class="span4">
            <h3>The Query</h3>
						<?php
							//echo "<pre class=\"prettyprint\">"; 
							echo "<code class=\"prettyprint lang-sql\">--\n";
							echo $query;
							echo "</code>";
							//echo "</pre>";
							echo "<div class=\"alert-message info\">";
							echo "<p>".($querytime)." sec </p>";
							echo "</div> <!-- alert -->";
						?>
          </div>
        </div>
      </div>
			
			<footer>
				<p>&copy; University of Florida 2011</p>
			</footer>
<?php
// Free resultset
pg_free_result($result);

// Closing connection
pg_close($dbconn);
?>
		</div> <!-- .container -->
	</body>
</html>
