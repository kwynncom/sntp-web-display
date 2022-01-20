<?php if (PHP_SAPI !== 'cli') { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>time offset with SNTP</title>

<style>
    table {  font-family: monospace }
    table.based, table.r1 { border-collapse: collapse; }
    td.host { padding-left: 1ex }
    tr { margin : 0ex; padding: 0 }
    td { padding: 0ex; padding-left: 1ex }
    td.sign { padding-left: 0.0ex; padding-right: 0.1ex; font-weight: bold; font-size: 120%}
    td.tar { text-align: right; }
    td.pl  { padding-left: 1ex }
    td.td1 { padding: 0ex }
    .avgcld { font-family: monospace }
    .fparent {
	  display: flex;
	  flex-flow: row wrap;
	  /* height: 90vh; */
	  align-content: flex-start;
	  align-items: flex-start;
    }
    
    .fchild { 
	flex-shrink: 0;
	border-style: solid;
	border-width: 2px; 
	padding: 0.5ex;
    }
    </style>
</head>
<body>
	<div> <!-- the whole dynamic output of SNTP -->
    <?php 
}
	require_once('out.php');
	echo sntp_report::report($dao); 
	if (PHP_SAPI !== 'cli') {
    ?>
	</div> <!-- the whole dynamic output of SNTP -->
</body>
</html>
<?php }
