#!/usr/bin/env php
<?php

$bench = new bench();
//change the next line to test different potential payload size 
$bench->run(64123);


class bench  {
	//loopnb = simulate number of frame processing
	protected $loopnb = 10000;
	//NO need to change anything beyong this line
	protected $mask      = 'aaaa';

	function run($samplesize) {
		$result[] = $this->test_current($samplesize);
		$result[] = $this->test_optimized($samplesize);
		$result[] = $this->test_optimizedv2($samplesize);
		$result[] = $this->test_strrepeat($samplesize);
		$result[] = $this->test_strpad($samplesize);
		sort($result);
		$result[0]['faster by ']= ($result[0]['rps']/$result[4]['rps']);
		$result[1]['faster by ']= ($result[1]['rps']/$result[4]['rps']);
		$result[2]['faster by ']= ($result[2]['rps']/$result[4]['rps']);
		$result[3]['faster by ']= ($result[3]['rps']/$result[4]['rps']);
		var_dump($result);
		//$this->display($result);
	}

    function test_current($samplesize) {
		echo("Testing current\n");
		for ($i=0;$i<$this->loopnb;$i++) {
			$effectiveMask="";
			$loop=0;
			$start = microtime(true);
				while (strlen($effectiveMask) < $samplesize) {
					$effectiveMask .= $this->mask;
					$loop++;
				}
				$over=strlen($effectiveMask)-$samplesize;
				while (strlen($effectiveMask) > $samplesize) {
					$effectiveMask = substr($effectiveMask,0,-1);
				}
			$end = microtime(true);
			$time= $end-$start;
			if (isset($result['total'])) {
				
				$result['total']+=$time;
				if ($time<$result['min']) $result['min']=$time;
				if ($time>$result['max']) $result['max']=$time;
			}
			else {
				$result['total']=$time;
				$result['name']='current';
				$result['min']=$time;
				$result['max']=$time;
				$result['nbloop']=$loop;
				$result['over']=$over;
				$result['check']=strlen($effectiveMask);
			}
		}
		$result['avg']=$result['total']/$this->loopnb;
		$result['rps']=1/$result['avg'];
		return $result;
	}

	function test_optimized($samplesize) {
		echo("Testing optimized............\n");
		for ($i=0;$i<$this->loopnb;$i++) {
			$effectiveMask=$this->mask;
			$loop=0;
			$start = microtime(true);
				while (strlen($effectiveMask) < $samplesize) {
					$effectiveMask .= $effectiveMask;
					$loop++;
				}
				$over=strlen($effectiveMask)-$samplesize;
				$effectiveMask=substr($effectiveMask,0,$samplesize);
			$end = microtime(true);
			$time= $end-$start;
			if (isset($result)) {
				$result['total']+=$time;
				if ($time<$result['min']) $result['min']=$time;
				if ($time>$result['max']) $result['max']=$time;
			}
			else {
				$result['total']=$time;
				$result['name']='optimized';
				$result['min']=$time;
				$result['max']=$time;
				$result['nbloop']=$loop;
				$result['over']=$over;
				$result['check']=strlen($effectiveMask);
			}
			
		}
		$result['avg']=$result['total']/$this->loopnb;
		$result['rps']=1/$result['avg'];
		return $result;
	}


	function test_optimizedv2($samplesize) {
		echo("Testing optimizedv2.......\n");
		for ($i=0;$i<$this->loopnb;$i++) {
			$effectiveMask=$this->mask;
			$loop=0;
			$masksize=4;
			$start = microtime(true);
				while ($masksize<$samplesize) {
					$effectiveMask .= $effectiveMask;
					$masksize+=$masksize;
					$loop++;
				}
				$over=$samplesize-strlen($effectiveMask);
				//remove overflow
				$effectiveMask=substr($effectiveMask,0,$over);
			$end = microtime(true);
			$time= $end-$start;
			if (isset($result)) {
				$result['total']+=$time;
				if ($time<$result['min']) $result['min']=$time;
				if ($time>$result['max']) $result['max']=$time;
			}
			else {
				$result['total']=$time;
				$result['name']='optimizedv2';
				$result['min']=$time;
				$result['max']=$time;
				$result['nbloop']=$loop;
				$result['over']=$over;
				$result['check']=strlen($effectiveMask);
			}
			
		}
		$result['avg']=$result['total']/$this->loopnb;
		$result['rps']=1/$result['avg'];
		return $result;
	}


function test_strpad($samplesize) {
		echo("Testing str_pad..................\n");
		for ($i=0;$i<$this->loopnb;$i++) {
			$effectiveMask="";
			$loop=0;
			$masksize=4;
			$start = microtime(true);
				$effectiveMask = str_pad("",$samplesize,$this->mask);
				$over=strlen($effectiveMask)-$samplesize;
			$end = microtime(true);
			$time= $end-$start;
			if (isset($result)) {
				$result['total']+=$time;
				if ($time<$result['min']) $result['min']=$time;
				if ($time>$result['max']) $result['max']=$time;
			}
			else {
				$result['total']=$time;
				$result['name']='str_pad';
				$result['min']=$time;
				$result['max']=$time;
				$result['nbloop']=$loop;
				$result['over']=$over;
				$result['check']=strlen($effectiveMask);
			}
			
		}
		$result['avg']=$result['total']/$this->loopnb;
		$result['rps']=1/$result['avg'];
		return $result;
	}
	function test_strrepeat($samplesize) {
		echo("Testing str_repeat..................\n");
		for ($i=0;$i<$this->loopnb;$i++) {
			$effectiveMask="";
			$loop=0;
			$masksize=4;
			$start = microtime(true);
				$effectiveMask = str_repeat($this->mask , ($samplesize/4)+1 );
				$over=$samplesize-strlen($effectiveMask);
				$effectiveMask=substr($effectiveMask,0,$samplesize);
			$end = microtime(true);
			$time= $end-$start;
			if (isset($result)) {
				$result['total']+=$time;
				if ($time<$result['min']) $result['min']=$time;
				if ($time>$result['max']) $result['max']=$time;
			}
			else {
				$result['total']=$time;
				$result['name']='str_repeat';
				$result['min']=$time;
				$result['max']=$time;
				$result['nbloop']=$loop;
				$result['over']=$over;
				$result['check']=strlen($effectiveMask);
			}
			
		}
		$result['avg']=$result['total']/$this->loopnb;
		$result['rps']=1/$result['avg'];
		return $result;
	}
}

?>
