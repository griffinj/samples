<?php
#
# projecteuler.com problem #22 solution
#

$values = array('A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7,'H'=>8,'I'=>9,
	   'J'=>10,'K'=>11,'L'=>12,'M'=>13,'N'=>14,'O'=>15,'P'=>16,'Q'=>17,
	   'R'=>18,'S'=>19,'T'=>20,'U'=>21,'V'=>22,'W'=>23,'X'=>24,'Y'=>25,
	   'Z'=>26);
#print_r($values);
$names = array_map('str_getcsv',file('names.txt'));
asort($names[0]);
$total = 0;
$positionCounter = 1;
foreach ($names as $name) {
	foreach($name as $name) {
		$nameValue = 0;
		for($x=0;$x<strlen($name);$x++) {
			$nameValue += $values[$name[$x]];		
		}
		$nameScore = $nameValue * $positionCounter;
		$positionCounter++;
	}
	$total += $nameScore;	
}
echo $total;
