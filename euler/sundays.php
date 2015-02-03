<?php

# counting sundays 1901->2000
# projecteuler.net problem 19 solution

$date = new DateTime;
$oneDay = new DateInterval('P1D');
$startDate = new DateTime();
$startDate->setDate(1901,1,1);
$endDate = new DateTime();
$endDate->setDate(2001,12,31);

$date->setDate(1900,1,1);
$numOfSundays = 0;
while ($date->format('Y:m:d') <= $endDate->format('Y:m:d')){
	if ($date->format('N') == 7 && $date->format('d') == 1 && $date->format('Y:m:d') > $startDate->format('Y:m:d')) {
		//echo $date->format('Y-m-d N')."\n";
		$numOfSundays++;
	}
	$date->add($oneDay);
}
echo $numOfSundays;
