<?php
echo __FILE__;
class BenchMark
{
	var $parameter = array();
	var $cmds = array();
	var $cmds_paras = array();
	var $log_file = "benchmark_logs";
	var $sge_path = "tmp_sge";
	var $default_sge_cmd = "
#!/bin/sh
#$ -V
#$ -S /bin/bash
";
	
	function BenchMark()
	{
		file_put_contents($this->log_file, "");
	}
	function add_parameter($name, $array)
	{
		$this->parameter[$name] = $array;
		
	}
	function run($cmd)
	{
		$this->make_cmds($cmd);
		foreach($this->cmds_paras as $cmd_para)
		{
			$cmd_idx = $cmd_para["cmd_idx"];
			$start_time =  microtime(true);
			shell_exec($this->cmds[$cmd_idx]);
			$end_time =  microtime(true);
			$log = number_format(($end_time - $start_time), 3, '.', '') . "\t" . json_encode($cmd_para) . "\n";
			file_put_contents($this->log_file, $log, FILE_APPEND);
		}
	}
	function sge_run($cmd)
	{
		@mkdir($this->sge_path);
		
		$this->make_cmds($cmd);
		foreach($this->cmds_paras as $cmd_para)
		{
			$cmd_idx = $cmd_para["cmd_idx"];
			$sge_cmd = $this->default_sge_cmd;
			if(isset($cmd_para["cpu"]))
				$sge_cmd .= "#$ -pe single " . ($cmd_para["cpu"]+2) . "\n";
			else if(isset($cmd_para["cpus"]))
				$sge_cmd .= "#$ -pe single " . ($cmd_para["cpus"]+2) . "\n";
			else
				$sge_cmd .= "#$ -pe single 2\n";
			$sge_cmd .= "#$ -o {$this->sge_path}/". md5($this->cmds[$cmd_idx]) .".log  -j y";
			$sge_cmd .= "#Parameter\t" . json_encode($cmd_para) . "\n";
			$sge_cmd .= "#cmd\t" . $this->cmds[$cmd_idx]."\n";
			
			$sge_cmd .= "date\n";
			$sge_cmd .= $this->cmds[$cmd_idx]."\n";
			$sge_cmd .= "date\n";
			
			file_put_contents($this->sge_path."/".md5($this->cmds[$cmd_idx]).".sge" , $sge_cmd);
		}
	}
	function make_cmds($cmd)
	{
		$total_cmd = 1;
		foreach($this->parameter as $name => $values)
		{
			$total_cmd *= count($values);
		}
		for($i=0;$i<$total_cmd;$i++)
			$this->cmds[] = $cmd;
		
		foreach($this->parameter as $name => $values)
		{
			$cmds_idx = 0;
			$values_count = count($values);
			for($i=0;$i < ($total_cmd / $values_count) ;$i++)
			{
				foreach($values as $value)
				{
					$this->cmds[$cmds_idx] = str_replace("\$$name", $value, $this->cmds[$cmds_idx]);
					$this->cmds_paras[$cmds_idx][$name] = $value;
					//$this->cmds_paras[$cmds_idx]["cmds_idx"] = $cmds_idx;
					$cmds_idx++;
				}
			}
		}
		foreach($this->cmds_paras as $idx => &$value)
		{
			$value["cmd_idx"] = $idx;
		}
		sort($this->cmds_paras);
	}
};



$bm = new BenchMark();
//$bm->add_parameter("repeat", array(1,2,3) );
$bm->add_parameter("genome", array(1,2,4,8,16) );

$bm->sge_run("time sleep \$genome");


?>