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
		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
		<script type="text/javascript">
			google.load('visualization', '1.0', {'packages':['annotatedtimeline']});
			google.setOnLoadCallback(drawVisualization);
			function drawVisualization() {
<?php
include 'util.php';

//error_reporting(E_ALL);
//ini_set('display_errors', '1');
// Build query

$player1 = $_GET['player1'];
$player2 = $_GET['player2'];
$from1 = date($_GET['from1']);
$to1 = date($_GET['to1']);
$num = date($_GET['num']);

$oldquery = "(select '$player1', created_at, cgrant_sentiment(twtext), twtext ".
	"\nfrom tweets ".
	"\nwhere (created_at >= '$from1' and created_at <= '$to1') ".
	"\nand (cgrant_distance(1,'$player1',2, twtext, 5) > .5) limit $num) ".
	"\nunion all".
	"\n(select '$player2', created_at, cgrant_sentiment(twtext), twtext ".
	"\nfrom tweets".
	"\nwhere (created_at >= '$from1' and created_at <= '$to1') ".
	"\nand (cgrant_distance(1,'$player2',2, twtext, 5) > .5) limit $num) ".
	//"\nlimit $num ".
	";";

// OPTOMIZED!!
$query = "select (CASE WHEN ".
	"(cgrant_distance(1,'$player1',2, twtext, 5) > .5) THEN '$player1' ".
	"ELSE '$player2' END), created_at, cgrant_sentiment(twtext), twtext ".
	"FROM tweets ".
	"WHERE created_at between '$from1' and '$to1' ".
	"AND ((cgrant_distance(1,'$player1',2, twtext, 5) > .5) OR ".
	"(cgrant_distance(1,'$player2',2, twtext, 5) > .5)) LIMIT $num ".
	";";


error_log($query."\n\n------------", 3, 'query.log');

// Connecting, selecting database
$_db = make_db_string();
$dbconn = pg_connect("host=".$_db->{'host'}." dbname=".$_db->{'db'}." user=".$_db->{'user'}." password=".$_db->{'pwd'}." options='--client_encoding=UTF8'")
    or die('Could not connect: ' . pg_last_error());

		list($tic_usec, $tic_sec) = explode(" ", microtime());
		$result = pg_query($query) or die('Query failed: ' . pg_last_error());
		list($toc_usec, $toc_sec) = explode(" ", microtime());
		$querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec);
?>
				var data = new google.visualization.DataTable();
				data.addColumn('datetime','Date'); // 0
				data.addColumn('number', '<?php echo $player1;?>'); // 1
				data.addColumn('string','title1'); // 2
				data.addColumn('string','text1'); // 3
				data.addColumn('number', '<?php echo $player2;?>'); // 4
				data.addColumn('string','title2'); // 5
				data.addColumn('string','text2'); // 6

<?php
				$result_count = pg_num_rows($result);
		
		echo "data.addRows(".pg_num_rows($result).");\n";
				$sent = 0;
				$counter = 0;
		while ($line = pg_fetch_array($result, null, PGSQL_NUM)) {
					if ($line[2] == "+") $sent = 1;
					if ($line[2] == "-") $sent = -1;
					$white = array("\t","\n","\r","\0","\x0B"); 
					$tweet = str_replace( $white, " ", addslashes($line[3]));
					if($line[0] == $player1){
						echo "data.setValue($counter, 0, new Date('".$line[1]."'));\n";	
						echo "data.setValue($counter, 1, $sent);\n";	
						echo "data.setValue($counter, 4, 0);\n";	
						echo "data.setValue($counter, 3, '$player1');\n";	
						echo "data.setValue($counter, 2, \"$tweet\");\n";	
						
					}
					else {
						echo "data.setValue($counter, 0, new Date('".$line[1]."'));\n";	
						echo "data.setValue($counter, 1, 0);\n";	
						echo "data.setValue($counter, 4, $sent);\n";	
						echo "data.setValue($counter, 6, '$player2');\n";	
						echo "data.setValue($counter, 5, \"$line[3]\");\n";	
					}
					$counter++;
			
		}
?>
        var chart = new google.visualization.AnnotatedTimeLine(document.getElementById('visualization'));

				chart.draw(data, {displayAnnotations:true, displayExactValues:true, legendPosition:'newRow',dateFormat: "HH:mm:ss 'on' yyyy.MM.dd G", thickness:3, fill:20, displayZoonButtons:false, highlightDot:'nearest'});
					
			}
		</script>
</head>

	<!-- <body  onload="prettyPrint()"> -->
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

			<div class="content">
        <div class="page-header">
				<h1>John Madden says <?php echo $_GET["q"];?>	
					<small>...</small>
				</h1>
        </div>
        <div class="row">
          <div class="span10">
            <h2>Answer</h2>
						<div id="visualization" style="height:365px; width:550px;"></div>

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
