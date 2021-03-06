<?php
class TrapidUtilsComponent extends Object{
	
  var $components	= array("Email","Cookie","Session");
  var $controller;

  function startup(&$c){
    $this->controller = & $c;
  }


  function getMsaLength($msa){
    $explode	= explode(">",$msa);
    $explode2	= explode(";",$explode[1]);
    $length	= strlen($explode2[1]);
    return $length;
  }


  function dirToArray($dir){
   $result = array();
   $cdir = scandir($dir);
   foreach ($cdir as $key => $value){
      if (!in_array($value,array(".",".."))){
         if (is_dir($dir . DIRECTORY_SEPARATOR . $value)){
            $result[$value] = $this->dirToArray($dir . DIRECTORY_SEPARATOR . $value);
         }
         else{
            $result[] = $value;
         }
      }
   }  
   return $result; 
  }


  
  function formatBytes($size,$precision=2){
    $base = log($size) / log(1024);
    $suffixes = array('bytes','Kb','Mb','Gb','Tb');
    return round(pow(1024,$base-floor($base)),$precision)." ".$suffixes[floor($base)];
  }



  function readDir($dir){
    $files = scandir($dir);
    $result = array();
    foreach($files as $f){
      if($f!="." && $f!=".."){
	$file_path = $dir."/".$f;
	$file_size = filesize($file_path);
	$result[] = array("filename"=>$f,"filepath"=>$file_path,"filesize"=>$this->formatBytes($file_size));	
      }
    }	 
    return $result; 
  }

  function getSubsetColorsATVConfig($config_file_path){
    $result	= array();
    if(file_exists($config_file_path)){
      $fh		= fopen($config_file_path,"r");
      $data_content	= fread($fh,filesize($config_file_path));	
      $data		= explode("\n",$data_content);     
      foreach($data as $d){
	if(strpos($d,"domain_color:")===0){
	  $r		= explode(" ",substr($d,14));
	  if(count($r)==5){
	  	$subset	= $r[0];
		$color	= $r[4];
		$color	= str_replace("0x","#",$color);
		$result[$subset]	= $color;
	  }
	}
      }
      fclose($fh);
    }    
    return $result;
  }



  function performExport($plaza_db,$email,$exp_id,$export_key,$filename,$filter=null){
    $tmp_dir		= TMP."experiment_data/".$exp_id."/";
    $ext_dir		= TMP_WEB."experiment_data/".$exp_id."/";
    $salt  		= $exp_id;
    $hash		= hash("sha256",$email."".$salt);
    $internal_file	= $tmp_dir."".$filename;
    $internal_zip	= $tmp_dir."".$hash.".zip";

    //create shell file
    $shell_file		= $tmp_dir."export_data.sh";
    $base_scripts_location	= APP."scripts/";
    $java_location	= $base_scripts_location."java/";
    $fh			= fopen($shell_file,"w");
    
    $java_program	= "transcript_pipeline.ExportManager";
    $java_params	= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
				TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
				$exp_id,$export_key,$internal_file);
    if($filter!=null){
      $java_params[]	= $filter;
    }

   //fwrite($fh,"module load java\n");    			
   //fwrite($fh,"java -cp ".$java_location.".:".$java_location."..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$java_params)."\n");
     fwrite($fh,"/usr/bin/java -cp ".$java_location.".:".$java_location."..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$java_params)." 2>&1 \n");
    fclose($fh);

    //execute shell script with java program
    shell_exec("chmod a+x ".$shell_file);
    $output = shell_exec("sh ".$shell_file);
    pr($output);

    //zip result file and cleanup
    if(file_exists($internal_zip)){
	shell_exec("rm -f ".$internal_zip);
    }
    shell_exec("zip -j ".$internal_zip." ".$internal_file);
    shell_exec("rm -f ".$internal_file);
    $result		= $ext_dir."".$hash.".zip";
    return $result;       
  }



  function createZipFileData($email,$exp_id,$data,$columnHeader,$filename){
    $tmp_dir		= TMP."experiment_data/".$exp_id."/";
    $ext_dir		= TMP_WEB."experiment_data/".$exp_id."/";
    $salt  		= $exp_id;
    $hash		= hash("sha256",$email."".$salt);
    $internal_file	= $tmp_dir."".$filename;
    $internal_zip	= $tmp_dir."".$hash.".zip";
    $fh			= fopen($internal_file,"w");
    foreach($columnHeader as $ch){
      fwrite($fh,$ch."\t");
    }
    fwrite($fh,"\n");

    foreach($data as $dat){     
      foreach($dat as $d){
	fwrite($fh,$d."\t");
      }	
      fwrite($fh,"\n");
    }
    fclose($fh);
    if(file_exists($internal_zip)){
	shell_exec("rm -f ".$internal_zip);
    }
    shell_exec("zip -j ".$internal_zip." ".$internal_file);
    shell_exec("rm -f ".$internal_file);
    $result		= $ext_dir."".$hash.".zip";
    return $result;

  }


  function createZipSeqFile($email,$exp_id,$data,$filetitle){
    $tmp_dir		= TMP."experiment_data/".$exp_id."/";
    $ext_dir		= TMP_WEB."experiment_data/".$exp_id."/";
    $salt  		= $exp_id;
    $hash		= hash("sha256",$email."".$salt);
    $internal_fasta	= $tmp_dir."".$filetitle;
    $internal_zip	= $tmp_dir."".$hash.".zip";
    $fh			= fopen($internal_fasta,"w");
    foreach($data as $k=>$v){
      fwrite($fh,">".$k."\n");
      fwrite($fh,$v."\n");   	
    }
    fclose($fh);
    if(file_exists($internal_zip)){
      shell_exec("rm -f ".$internal_zip);
    }
    shell_exec("zip -j ".$internal_zip." ".$internal_fasta);
    shell_exec("rm -f ".$internal_fasta);
    $result		= $ext_dir."".$hash.".zip";
    return $result;
  }



  function checkPageAccess($exp_name,$process_state,$allowed_states){
    if(in_array($process_state,$allowed_states)){return;}
    else{
      //$this->Session->setFlash("Experiment '".$exp_name."' is in wrong state (".$process_state.") for web-access");
	$this->controller->redirect(array("controller"=>"trapid","action"=>"experiments"));
    }
  }



  function indexToGoTypes($go_data,$table_name,$column_name_go,$column_name_type){
    $result		= array();
    foreach($go_data as $gd){
      $go		= $gd[$table_name][$column_name_go];
      $type		= $gd[$table_name][$column_name_type];
      if(!array_key_exists($type,$result)){$result[$type] = array();}
      $result[$type][] = $go;
    }
    return $result;	
  }



  function unify($data){
    $result	= array();
    foreach($data as $d){
      $res	= array();
      foreach($d as $table=>$columns){
	$res = array_merge($res,$columns);
      }
      $result[] = $res;
    }   
    return $result;
  }

  function reduceArray($model_data,$table_name,$column_name){
    $result	= array();
    foreach($model_data as $md){
      $result[]	= $md[$table_name][$column_name];
    }
    return $result;
  }


  function valueToIndexArray($data){
    $result	= array();
    foreach($data as $d){
      $result[$d] = $d;
    }
    return $result;
  }
	
  function indexArrayMulti($data,$table_name,$column_key,$columns_val){
    $result		= array();
    foreach($data as $d){
      $d1	= $d[$table_name][$column_key];
      $res	= array();
      foreach($columns_val as $cv){
	$res[$cv]	= $d[$table_name][$cv];
      }
      $result[$d1]	= $res;
    }
    return $result;
  }
								       

  function indexArraySimple($data,$table_name,$column_key,$column_val){
    $result =	array();
    foreach($data as $d){
      $d1	= $d[$table_name][$column_key];
      $d2	= $d[$table_name][$column_val];
      $result[$d1] = $d2;     
    }
    return $result;	
  }

  function indexArray($data,$table_name,$column_key,$column_val){
    $result =	array();
    foreach($data as $d){
      $d1	= $d[$table_name][$column_key];
      $d2	= $d[$table_name][$column_val];
      if(!array_key_exists($d1,$result)){$result[$d1]= array();}
      $result[$d1][] = $d2;
    }
    return $result;
  }


  function checkAvailableRapsearchDB($plaza_db,$data){
    $result		= array();
    $final_blast_dir	= BLAST_DB_DIR."".$plaza_db."/";
    foreach($data as $k=>$v){
      //check whether file with necessary name exists in the directory. If so, add to result.
      $blast_db		= $final_blast_dir."".$k.".rap";    
      //pr($blast_db."\t".$v);
      if(file_exists($blast_db)){$result[$k] = $v;}
    }
    return $result;
  } 

  function checkJobStatus($exp_id,$jobs_data){
    $tmp_dir		= TMP."experiment_data/".$exp_id."/";
    $qstat_script	= $this->create_qstat_script($exp_id);
    $result		= array();

    //get overview of all jobs on webcluster?
    $shell_output_all	= array();
    $command_all	= "sh $qstat_script -u apache 2>&1";
    exec($command_all,$shell_output_all);
    $job_details	= array();
    for($i=2;$i< count($shell_output_all);$i++){
      $job_det		= explode(" ",$shell_output_all[$i]);
      $jd		= array();
      foreach($job_det as $jde){if($jde){$jd[]  = $jde;}}
      $job_details[$jd[0]] = $jd[4];
    }
    foreach($jobs_data as $t=>$jd){
      $job_id		= $jd['job_id'];
      $job_status	= "done";	//default: job does not exists anymore, or wrongfully inserted data
      if(array_key_exists($job_id,$job_details)){
	$js		= $job_details[$job_id];
	if($js=="Eqw"){$job_status="error";}
	else if($js=="qw"){$job_status="queued";}
	else if($js=="r"){$job_status="running";}
	else{$job_status="unknown";}
      }
      $result[$job_id]	= $jd;
      $result[$job_id]['status'] = $job_status;
    }
    return $result;
  }


  


  function waitfor_cluster($exp_id,$job_id,$max_time=60,$interval=4){
    //first: remove all files older than X days in the folder?
    $tmp_dir		= TMP."experiment_data/".$exp_id."/";
    shell_exec("find $tmp_dir -maxdepth 1 -atime +8 -type f -exec rm -f {} \\;"); 

     $result			= array();	
     $qstat_script  		= $this->create_qstat_script($exp_id);
     $qdel_script		= $this->create_qdel_script($exp_id);
     $cont			= true;
     $total_counter 		= 0;
     $max_counter		= $max_time;   
     $command			= "sh $qstat_script -j $job_id 2>&1"; 
     while($cont){       
      //ok, check using qstat whether the job has finished yet. Maximum seconds counter added
      //to prevent eternal loop.
      $out		= array();
      exec($command,$out);
      if($out[0]=="Following jobs do not exist:"){
	$cont		= false;
      }   
      sleep($interval);     
      $total_counter	+= $interval;
      if($total_counter>$max_counter){
	//perform kill command for job, so - even if it is stuck in the queue - it gets killed.
	$result["error"]	= "Job was not finished in appropiate amount of time.";
	exec("sh $qdel_script $job_id");
	return $result;
      }
    }
    $result["success"]	= "ok";
    return $result;       
  }


  function sync_file($file,$max_sync_time=10){
    $cont		= true;
    $sync_time		= 0;
    $sync_time_interval = 1;
    while($cont){
      if(file_exists($file)){return $file;}
      sleep($sync_time_interval);
      $sync_time	+=$sync_time_interval;
      if($sync_time>$max_sync_time){$cont=false;}
    }
    return false;
  }





  function getClusterJobId($qsub_output){
    if(count($qsub_output)==0){return null;}  
    $qs		= explode(" ",$qsub_output[0]);  
    $job_id    = $qs[2];   
    if(is_numeric($job_id)){return $job_id;}
    return null;
  }


  function get_all_processing_experiments(){
    	$result			= array();	
  	$base_scripts_location	= APP."scripts/";
	$running_jobs		= shell_exec("sh ".$base_scripts_location."shell/get_all_jobids.sh ");
        foreach(explode("\n",$running_jobs) as $job_id){
	  if(is_numeric($job_id)){
	    //get shell-script name for this job-id
	    $job_info		= shell_exec("sh ".$base_scripts_location."shell/check_job_id.sh ".$job_id);
	    $job_data		= explode("\n",$job_info);
	    foreach($job_data as $jd){
	      $jd2		= explode("\t",$jd);
	      if(count($jd2)==2 && $jd2[0]=="script_file:" && strpos($jd2[1],"initial_processing")!==FALSE){
		$start		= strpos($jd2[1],"initial_processing")+19;	       
		$exp_id		= substr($jd2[1],$start);
		$exp_id		= substr($exp_id,0,strpos($exp_id,".sh"));
		$result[$exp_id] = $job_id;
	      }
	    }		
	  }
	}
	return $result;
  }


  function delete_job($job_id){
	$base_scripts_location	= APP."scripts/";
	shell_exec("sh ".$base_scripts_location."shell/delete_job.sh ".$job_id);	
  }

  function deleteClusterJob($exp_id,$job_id){
    $qdel_script	= $this->create_qdel_script($exp_id);
    shell_exec("sh ".$qdel_script." ".$job_id);
  }


  function create_qstat_script($exp_id){
	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$qstat_file		= $tmp_dir."qstat.sh";
	$fh			= fopen($qstat_file,"w");       
	fwrite($fh,"#!/bin/bash \n");
	fwrite($fh,". /etc/profile.d/settings.sh\n");
	fwrite($fh,"qstat $* \n");
	fclose($fh);
	shell_exec("chmod a+x ".$qstat_file);
	return $qstat_file;	
  }


  function create_qdel_script($exp_id){
	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$qdel_file		= $tmp_dir."qdel.sh";
	$fh			= fopen($qdel_file,"w");       
	fwrite($fh,"#!/bin/bash \n");
	fwrite($fh,". /etc/profile.d/settings.sh\n");
	fwrite($fh,"qdel $* \n");
	fclose($fh);
	shell_exec("chmod a+x ".$qdel_file);
	return $qdel_file;
  }



  function create_qsub_script_general(){
        $tmp_dir		= TMP."experiment_data/";
        $qsub_file		= $tmp_dir."qsub.sh";
	if(!file_exists($qsub_file)){
	    $fh			= fopen($qsub_file,"w");       
	    fwrite($fh,"#!/bin/bash \n");
	    fwrite($fh,". /etc/profile.d/settings.sh\n");
	    fwrite($fh,"qsub $* \n");
	    fclose($fh);
	    shell_exec("chmod a+x ".$qsub_file);
	}
	return $qsub_file;
  }


  function create_qsub_script($exp_id){
	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	//remove all the files older than 3 days in this directory, to save disk space.
	shell_exec("find $tmp_dir".'.'." -maxdepth 2 -atime +3 -type f -exec rm -f {} \\;");	       
	$qsub_file		= $tmp_dir."qsub.sh";
	if(!file_exists($qsub_file)){
	    $fh			= fopen($qsub_file,"w");       
	    fwrite($fh,"#!/bin/bash \n");
	    fwrite($fh,". /etc/profile.d/settings.sh\n");
	    fwrite($fh,"qsub $* \n");
	    fclose($fh);
	    shell_exec("chmod a+x ".$qsub_file);
	}
	return $qsub_file;
  }



  
  function create_monthly_cleanup_script($year,$month,$cleanup_warning,$cleanup_delete){
    $tmp_dir		= TMP."experiment_data/";
    $shell_script	= $tmp_dir."cleanup_".$year."_".$month.".sh";       
    $perl_script	= APP."scripts/perl/monthly_cleanup.pl";    
    $necessary_modules	= array("perl");        	    
    $params		= array(
				TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
      				TMP."experiment_data",$year,$month,$cleanup_warning,$cleanup_delete			
      				);

    $fh			= fopen($shell_script,"w");
    fwrite($fh,"#Loading necessary modules\n");
    foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
    }
    fwrite($fh,"\n#Launching cleanup program\n");   	
    fwrite($fh,"perl ".$perl_script." ".implode(" ",$params)."\n");
    fclose($fh);
    shell_exec("chmod a+x ".$shell_script);
    return $shell_script;     
  }



  function create_shell_script_data_update_gf($exp_id,$plaza_db,$gf_id,$transcript_id,$new_gf=false){
    $base_scripts_location	= APP."scripts/";
    $tmp_dir			= TMP."experiment_data/".$exp_id."/";
    $necessary_modules		= array("java");
    //create actual shell script file
    $shell_file			= $tmp_dir."gf_change_".$exp_id."_".$transcript_id."_".$gf_id.".sh";
    $fh				= fopen($shell_file,"w");
    fwrite($fh,"#Loading necessary modules\n");
    foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
    }
    $parameters			= array();
    if($new_gf){
      $parameters		= array("GF_ASSOC_NEW",
					PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$exp_id,$transcript_id,$gf_id
				      );
    }
    else{
      $parameters		= array("GF_ASSOC_EXIST",
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$exp_id,$transcript_id,$gf_id
				     );
    }

    
    $java_location		= $base_scripts_location."java/";
    $java_program		= "transcript_pipeline.UpdateData";
       
    fwrite($fh,"\n#Launching java program\n");
    fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$parameters)."\n");	
    fclose($fh);
    shell_exec("chmod a+x ".$shell_file);
    return $shell_file;
  }


  function create_shell_script_tree($exp_id,$plaza_db,$gf_id,$editing_mode,$bootstrap_mode,$tree_program,$include_subsets,$include_meta){
    $inc_sub = 0; if($include_subsets){$inc_sub=1;}
    $inc_met = 0; if($include_meta){$inc_met=1;}
    $base_scripts_location	= APP."scripts/";
    $tmp_dir			= TMP."experiment_data/".$exp_id."/";
    $necessary_modules		= array("perl","muscle","java");
    $necessary_modules[]	= $tree_program;

    //create actual shell script file
    $shell_file			= $tmp_dir."create_tree_".$gf_id.".sh";
    $fh				= fopen($shell_file,"w");
    fwrite($fh,"#Loading necessary modules\n");
    foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
    }
    $java_location	 	= $base_scripts_location."java/";	

    $parameters_msa		= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_PORT,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$tmp_dir,$exp_id,$gf_id,			
					$base_scripts_location."perl/blosum62.txt",
					$editing_mode
					);	

    $parameters_tree		= array(TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$tmp_dir,$exp_id,$gf_id,$bootstrap_mode,$tree_program);

    $parameters_phyloxml	= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					"db_trapid_01_taxonomy",
					$exp_id,$gf_id,$inc_sub,$inc_met,$tmp_dir,
					$java_location."forester.jar",
					$base_scripts_location."cfg/atv_config_file.cfg"
					);

    fwrite($fh,"\n#Launching perl script for creating necessary files, then MSA \n");      
    $program_location_msa	= $base_scripts_location."perl/create_msa.pl";
    $command_line_msa		= "perl ".$program_location_msa." ".implode(" ",$parameters_msa);
    $program_location_tree	= $base_scripts_location."perl/create_tree.pl";
    $command_line_tree		= "perl ".$program_location_tree." ".implode(" ",$parameters_tree);     
    $program_phyloxml		= "transcript_pipeline.CreatePhyloXML";
    $command_line_phyloxml	= "java -cp ".$java_location.":.:..:".$java_location."mysql.jar ".$program_phyloxml." ".implode(" ",$parameters_phyloxml)."\n";

    fwrite($fh,$command_line_msa."\n");
    fwrite($fh,$command_line_tree."\n");
    fwrite($fh,$command_line_phyloxml."\n");
    fclose($fh);
    shell_exec("chmod a+x ".$shell_file);
    return $shell_file;
  }


  function create_shell_script_msa($exp_id,$plaza_db,$gf_id,$editing_mode=null){
    $base_scripts_location	= APP."scripts/";
    $tmp_dir			= TMP."experiment_data/".$exp_id."/";
    $necessary_modules		= array("perl","muscle");
    //create actual shell script file
    $shell_file			= $tmp_dir."create_msa_".$gf_id.".sh";
    $fh				= fopen($shell_file,"w");
    fwrite($fh,"#Loading necessary modules\n");
    foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
    }
    $parameters			= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_PORT,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$tmp_dir,$exp_id,$gf_id,				
					$base_scripts_location."perl/blosum62.txt"
					);	    

    fwrite($fh,"\n#Launching perl script for creating necessary files, then MSA \n");      
    $program_location		= $base_scripts_location."perl/create_msa.pl";
    $command_line		= "perl ".$program_location." ".implode(" ",$parameters);
    fwrite($fh,$command_line."\n");
    fclose($fh);
    shell_exec("chmod a+x ".$shell_file);
    return $shell_file;
  }



  //function to create shell file for go enrichment
  function create_shell_file_enrichment($exp_id,$type,$plaza_db,$fa_file_all,$fa_file_subset,$result_file,$subset,$pvalue){
	$base_scripts_location	= APP."scripts/";   
       	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$necessary_modules	= array("java");
        //create actual file
      	$shell_file		= $tmp_dir.$type."_enrichment_".$exp_id."_".$subset.".sh";
      	$fh 			= fopen($shell_file,"w");	
	fwrite($fh,"#Loading necessary modules\n");
	foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
	}
	

	$java_params1		= array(TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$exp_id,$type,$fa_file_all,$fa_file_subset,$subset);
	$java_params2		= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					$type,$fa_file_all,$fa_file_subset,$result_file,$pvalue,"false");

	$java_location		= $base_scripts_location."java/";
	$java_program1		= "transcript_pipeline.PrepareEnrichment";
	$java_program2		= "transcript_pipeline.GeneralEnrichment";
	fwrite($fh,"\n#Launching java program for file creation\n");
	fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program1." ".implode(" ",$java_params1)."\n");
	fwrite($fh,"\n#Launching java program for Go enrichment\n");
	fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program2." ".implode(" ",$java_params2)."\n");	 
	fwrite($fh,"\n#Deleting transcript-go files\n");
	fwrite($fh,"rm -f ".$fa_file_all."\n");
	fwrite($fh,"rm -f ".$fa_file_subset."\n");
	fclose($fh);
	shell_exec("chmod a+x ".$shell_file);
	return $shell_file;
  }


  //function to create shell script for framedp evaluation
  function create_shell_script_framedp($exp_id,$plaza_db,$gf_id,$selected_transcripts,$extra_transcripts){
	$base_scripts_location	= APP."scripts/";   
       	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	//$necessary_modules	= array("java","framedp");
	//$necessary_modules	= array("java","framedp/x86_64/1.0.3");
	$necessary_modules	= array("java","perl/x86_64/5.8.9","framedp/x86_64/1.0.3");
 	//$necessary_modules	= array("java","framedp/x86_64/1.2.0");

	//directory init
	$framedp_dir		= $tmp_dir."framedp/";
	//shell_exec("rm -rf ".$framedp_dir."/*");

	$framedp_dir_evalgf	= $framedp_dir."".$gf_id."/";
	      	
	if(!(file_exists($framedp_dir_evalgf) && is_dir($framedp_dir_evalgf))){
	  mkdir($framedp_dir_evalgf);
	  shell_exec("chmod a+rw ".$framedp_dir_evalgf);
	}
	else{
	    //if persons are running 2 seperate framedp instances within the same gene family, 
	    //this will create probably some errors. It's their own fault though.  	
	    shell_exec("rm -rf ".$framedp_dir_evalgf."*");
	}

	//create file containing the names of only the selected transcripts.
	//this way the post-processing will only change the selected transcripts, and not the 
	//additional extra transcripts, since this would perhaps not be appreciated (and could potentially change
	//non-frameshifted sequences into frameshifted sequences in the worst case
	//Although this probably means we're throwing away most of the processed data, I think this is 
	//the best solution.
	$selected_file		= $framedp_dir_evalgf."/eval_".$gf_id.".txt";
	$fh_selected_file	= fopen($selected_file,"w");
	foreach($selected_transcripts as $k=>$v){
	  fwrite($fh_selected_file,$k."\n");
	}
	fclose($fh_selected_file);

	//create multi-fasta file
	$multifasta_file	= $framedp_dir_evalgf."/eval_".$gf_id.".fasta";
	$fh_fasta		= fopen($multifasta_file,"w");
	foreach($selected_transcripts as $k=>$v){
	  fwrite($fh_fasta,">".$k."\n");
	  fwrite($fh_fasta,$v."\n");	
	}
	//add the extra transcripts necessary for training
	foreach($extra_transcripts as $k=>$v){
	  fwrite($fh_fasta,">".$k."\n");
	  fwrite($fh_fasta,$v."\n");
	}
	fclose($fh_fasta); 
	
	//create shell script
	$cfg_file		= $framedp_dir."FrameDP.cfg";
	if(!file_exists($cfg_file)){	
		$final_blast_dir	= BLAST_DB_DIR."".$plaza_db."/";
		$final_blast_dir_midas	= BLAST_DB_DIR_MIDAS."".$plaza_db."/";
		copy($base_scripts_location."cfg/FrameDP.cfg",$cfg_file);
		$fh_cfg			= fopen($cfg_file,"a");
		fwrite($fh_cfg,"reference_protein_database=".$final_blast_dir."all_proteins\n");
		fwrite($fh_cfg,"#reference_protein_database=".$final_blast_dir_midas."all_proteins\n");
		fclose($fh_cfg);
		shell_exec("chmod a+rw ".$cfg_file);
	}

	$shell_file		= $framedp_dir_evalgf."/run_framedp.sh";
	$fh 			= fopen($shell_file,"w");
	fwrite($fh,"#Loading necessary modules\n");
	foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
	}
	$gff_result_file	= $framedp_dir."000/".$gf_id."/".$gf_id.".gff3";
	
	fwrite($fh,"echo \"Testing BLAST locations\"\n");
	fwrite($fh,"ls -l ".BLAST_DB_DIR."".$plaza_db."/all_proteins*\n");
	fwrite($fh,"ls -l /software/shared/apps/x86_64/blast/2.2.17/bin//blastall\n");
	fwrite($fh,"ls -l /software/shared/apps/x86_64/blast/2.2.17/bin//formatdb\n");
	fwrite($fh,"#start detection of formatdb executable\n");
	fwrite($fh,"ls -l /software/shared/apps/x86_64/framedp/1.0.3/bin/ext/ncbi-blast/bin/formatdb\n");
	fwrite($fh,"#end detection of formatdb executable\n");
	fwrite($fh,"\n\n\n");       
	fwrite($fh,"echo \"Starting frameDP\"\n");
	fwrite($fh,"date\n");
	fwrite($fh,"FrameDP.pl --cfg=".$cfg_file." --infile=".$multifasta_file." --outdir=".$framedp_dir_evalgf." --workingdir=".$framedp_dir_evalgf." --verbose \n");	
	fwrite($fh,"date\n");
	fwrite($fh,"echo \"Ending framedp\"\n");

	fwrite($fh,"echo \"Starting java postprocessing\"\n");
	//call the java program which will evaluate the framedp results, and update the database
	$java_parameters	= array("check_evaluation_output",TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$exp_id,$framedp_dir_evalgf,$selected_file,$multifasta_file);
	$java_location		= $base_scripts_location."java/";
	$java_program		= "transcript_pipeline.FrameDPProgram";
	fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$java_parameters)."\n");
	fwrite($fh,"echo \"Stopping java postprocessing\"\n");

	fwrite($fh,"chmod --recursive a+rwx ".$framedp_dir_evalgf."\n");
	fwrite($fh,"chmod --recursive a+rwx ".$framedp_dir_evalgf."000\n");

	//now, remove old eval content
	fwrite($fh,"echo \"Removing temp dir \" \n");
	fwrite($fh,"rm -rf ".$framedp_dir_evalgf."\n");	
	fwrite($fh,"rmdir ".$framedp_dir_evalgf."\n");

	//close up connection, email and check job queue for this job
	//$cleanup_location	= $base_scripts_location."perl/";
	$cleanup_parameters	= array(TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$exp_id,$gf_id);

	$cleanup_program_location	= $base_scripts_location."perl/cleanup_framedp.pl";
	$command_line			= "perl ".$cleanup_program_location." ".implode(" ",$cleanup_parameters);
	fwrite($fh,$command_line."\n");	

	fclose($fh);
	shell_exec("chmod a+x ".$shell_file);
	return $shell_file;
  }



  //function to create shell script for framedp evaluation
  function create_shell_script_framedp_old($exp_id,$plaza_db,$gf_id,$selected_transcripts){
	$base_scripts_location	= APP."scripts/";   
       	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$necessary_modules	= array("perl","java","framedp");
	//directory init
	$framedp_dir		= $tmp_dir."framedp/";
	$framedp_dir_training	= $framedp_dir."training/";
	$framedp_dir_eval	= $framedp_dir."evaluation/";
	if(!(file_exists($framedp_dir_eval) && is_dir($framedp_dir_eval))){
	  mkdir($framedp_dir_eval);
	  shell_exec("chmod a+rw ".$framedp_dir_eval);
	}
	$framedp_dir_evalgf	= $framedp_dir_eval."".$gf_id;
	if(!(file_exists($framedp_dir_evalgf) && is_dir($framedp_dir_evalgf))){
	  mkdir($framedp_dir_evalgf);
	  shell_exec("chmod a+rw ".$framedp_dir_evalgf);
	}
	//remove potential restants from previous runs
	shell_exec("rm -rf ".$framedp_dir_evalgf."/*");
	shell_exec("rm -rf ".$framedp_dir_training."000");

	//create multi-fasta file
	$multifasta_file	= $framedp_dir_evalgf."/eval_".$gf_id.".fasta";
	$fh_fasta		= fopen($multifasta_file,"w");
	foreach($selected_transcripts as $k=>$v){
	  fwrite($fh_fasta,">".$k."\n");
	  fwrite($fh_fasta,$v."\n");	
	}
	fclose($fh_fasta);		
	
	//create shell script
	$cfg_file		= $framedp_dir."FrameDP.cfg";
	if(!file_exists($cfg_file)){	
		$final_blast_dir	= BLAST_DB_DIR."".$plaza_db."/";
		copy($base_scripts_location."cfg/FrameDP.cfg",$cfg_file);
		$fh_cfg			= fopen($cfg_file,"a");
		fwrite($fh_cfg,"\n\nreference_protein_database=".$final_blast_dir."all_proteins\n");
		fclose($fh_cfg);
	}

	$shell_file		= $framedp_dir_evalgf."/run_framedp.sh";
	$fh 			= fopen($shell_file,"w");
	fwrite($fh,"#Loading necessary modules\n");
	foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
	}
	$gff_result_file	= $framedp_dir_training."000/".$gf_id."/".$gf_id.".gff3";
	
	
	//fwrite($fh,"@ counter=1\n");
	//fwrite($fh,"while (\$counter<3 && ! -e ".$gff_result_file.") \n");
	//fwrite($fh,"ps | grep `echo $$` | awk '{ print $4 }'  \n");
	fwrite($fh,"echo \"Starting frameDP\"\n");
	fwrite($fh,"date\n");
	fwrite($fh,"FrameDP.pl --cfg=".$cfg_file." --infile=".$multifasta_file." --outdir=".$framedp_dir_training." --workingdir=".$framedp_dir_evalgf." --no_train --verbose \n");	
	fwrite($fh,"date\n");
	fwrite($fh,"echo \"Ending framedp\"\n");
	//fwrite($fh,"@ counter = \$counter + 1	\n");
	//fwrite($fh,"end\n");
	//ok, wait for framedp to end.	

	fwrite($fh,"echo \"Starting java postprocessing\"\n");
	//call the java program which will evaluate the framedp results, and update the database
	$java_parameters	= array("check_evaluation_output",TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$exp_id,$framedp_dir_training,$multifasta_file);
	$java_location		= $base_scripts_location."java/";
	$java_program		= "transcript_pipeline.FrameDPProgram";
	fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$java_parameters)."\n");
	fwrite($fh,"echo \"Stopping java postprocessing\"\n");

	fwrite($fh,"chmod --recursive a+rwx ".$framedp_dir_evalgf."\n");
	fwrite($fh,"chmod --recursive a+rwx ".$framedp_dir_training."000\n");

	//now, remove old eval content
	fwrite($fh,"echo \"Removing temp dir \"");
	fwrite($fh,"rm -rf ".$framedp_dir_evalgf."\n");	

	fclose($fh);
	shell_exec("chmod a+x ".$shell_file);
	return $shell_file;
  }



  function create_shell_file_upload($exp_id,$upload_dir){
    //pr($upload_dir);
	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$base_scripts_location  = APP."scripts/";
	$necessary_modules 	= array("perl");
	$necessary_parameters   = array(TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$upload_dir,$exp_id,$base_scripts_location
					);
	//create actual file
	$shell_file		= $tmp_dir."database_upload.sh";
	$fh			= fopen($shell_file,"w");
	fwrite($fh,"#Loading necessary modules\n");
	foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
	}
	fwrite($fh,"\n#Launching perl script for database upload, with necessary parameters\n");
	$program_location	= $base_scripts_location."perl/database_upload.pl";
	$command_line		= "perl ".$program_location." ".implode(" ",$necessary_parameters);
	fwrite($fh,$command_line."\n");
	fclose($fh);	
       
	shell_exec("chmod a+x ".$shell_file);
	return $shell_file;
  }




  //function to create the necessary shell-files
  function create_shell_file_initial($exp_id,$plaza_db,$blast_db,$gf_type,$num_top_hits,$evalue,$func_annot){
	$num_training_framedp	= 50;		

        $base_scripts_location  = APP."scripts/";   	
	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$final_blast_dir	= BLAST_DB_DIR."".$plaza_db."/";
    	$necessary_modules	= array("perl","java","framedp");
    	$necessary_parameters	= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_PORT,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$tmp_dir,$exp_id,$final_blast_dir,$blast_db.".rap",$gf_type,$num_top_hits,$evalue,$func_annot,
					$base_scripts_location
					);
	
	//create actual file
      	$shell_file		= $tmp_dir."initial_processing_".$exp_id.".sh";
      	$fh 			= fopen($shell_file,"w");	
	fwrite($fh,"#Loading necessary modules\n");
	foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
	}
	fwrite($fh,"\n#Launching perl script for initial processing, with necessary parameters\n");
	//$program_location	= "/www/group/biocomp/extra/bioinformatics_prod/webtools/trapid/app/scripts/perl/initial_processing.pl";
	$program_location	= $base_scripts_location."perl/initial_processing.pl";
	$command_line		= "perl ".$program_location." ".implode(" ",$necessary_parameters);
	fwrite($fh,$command_line."\n");

	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////// FRAMEDP PREPROCESSING ///////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////

	//CUT OUT PRE-TRAINING PHASE (OLD VERSION IS STILL IN SOURCE) TO PREVENT THE POSSIBLE

	//ok, second step of the initial processing: the framedp part.
	//create the directory
	$framedp_dir		= $tmp_dir."framedp/";
	if(!(file_exists($framedp_dir) && is_dir($framedp_dir))){
	  mkdir($framedp_dir);
	  shell_exec("chmod a+rw ".$framedp_dir);
	}
	shell_exec("rm -rf ".$framedp_dir."*");	
	fclose($fh);

	shell_exec("chmod a+x ".$shell_file);
	return $shell_file;
  }


  //function to create the necessary shell-files
  function create_shell_file_initial_old($exp_id,$plaza_db,$blast_db,$gf_type,$num_top_hits,$evalue,$func_annot){
	$num_training_framedp	= 50;		

        $base_scripts_location  = APP."scripts/";   	
	$tmp_dir		= TMP."experiment_data/".$exp_id."/";
	$final_blast_dir	= BLAST_DB_DIR."".$plaza_db."/";
    	$necessary_modules	= array("perl","java","framedp");
    	$necessary_parameters	= array(PLAZA_DB_SERVER,$plaza_db,PLAZA_DB_PORT,PLAZA_DB_USER,PLAZA_DB_PASSWORD,
					TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_PORT,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
					$tmp_dir,$exp_id,$final_blast_dir,$blast_db.".rap",$gf_type,$num_top_hits,$evalue,$func_annot,
					$base_scripts_location
					);
	
	//create actual file
      	$shell_file		= $tmp_dir."initial_processing_".$exp_id.".sh";
      	$fh 			= fopen($shell_file,"w");	
	fwrite($fh,"#Loading necessary modules\n");
	foreach($necessary_modules as $nm){
	  fwrite($fh,"module load ".$nm." \n");
	}
	fwrite($fh,"\n#Launching perl script for initial processing, with necessary parameters\n");
	//$program_location	= "/www/group/biocomp/extra/bioinformatics_prod/webtools/trapid/app/scripts/perl/initial_processing.pl";
	$program_location	= $base_scripts_location."perl/initial_processing.pl";
	$command_line		= "perl ".$program_location." ".implode(" ",$necessary_parameters);
	fwrite($fh,$command_line."\n");

	//////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////// FRAMEDP PREPROCESSING ///////////////////////////////////////////////////
	//////////////////////////////////////////////////////////////////////////////////////////////

	//ok, second step of the initial processing: the framedp part.
	//create the directory
	$framedp_dir		= $tmp_dir."framedp/";
	if(!(file_exists($framedp_dir) && is_dir($framedp_dir))){
	  mkdir($framedp_dir);
	  shell_exec("chmod a+rw ".$framedp_dir);
	}
	shell_exec("rm -rf ".$framedp_dir."*");
	$framedp_dir_training	= $framedp_dir."training/";
	if(!(file_exists($framedp_dir_training) && is_dir($framedp_dir_training))){
	  mkdir($framedp_dir_training);
	  shell_exec("chmod a+rw ".$framedp_dir_training);
	}


	$transcript_seq_file	= $framedp_dir."training.fasta";

	//Java program creates training sequences for the frameDP program, but also checks first whether the experiment is in a 
	//finished state, and later on updates the framedp state
       	$java_parameters	= array("create_training_file",TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
							$exp_id,$num_training_framedp,$transcript_seq_file);
	fwrite($fh,"#Extracting training sequences for FrameDp\n");
	$java_location		= $base_scripts_location."java/";
	$java_program		= "transcript_pipeline.FrameDPProgram";
	fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$java_parameters)."\n");
	
	//ok, copy the configuration file from the default location, to the experiment page. Append location of blast database 
	//since this blast database is dependent on the selected reference database.
	$cfg_file		= $framedp_dir."FrameDP.cfg";
	copy($base_scripts_location."cfg/FrameDP.cfg",$cfg_file);
	$fh_cfg			= fopen($cfg_file,"a");
	fwrite($fh_cfg,"\n\nreference_protein_database=".$final_blast_dir."all_proteins\n");
	fclose($fh_cfg);
	
	//launch framedp itself	       
	fwrite($fh,"echo \"Starting frameDP\"\n");
	fwrite($fh,"FrameDP.pl --cfg=".$cfg_file." --infile=".$transcript_seq_file." --outdir=".$framedp_dir_training."\n");
	fwrite($fh,"echo \"Ending framedp\"\n");
	
	//remove all files from the training directory, except for the ones that are still needed 
	//done by copying data then removing original folder
	/*
	$framedp_dir_finaltraining	= $framedp_dir."training/";
       	if(!(file_exists($framedp_dir_finaltraining) && is_dir($framedp_dir_finaltraining))){
	  mkdir($framedp_dir_finaltraining);
	  shell_exec("chmod a+rw ".$framedp_dir_finaltraining);
	}
	fwrite($fh,"cp ".$framedp_dir_training."*.par ".$framedp_dir_finaltraining."\n");
	fwrite($fh,"cp ".$framedp_dir_training."*.info ".$framedp_dir_finaltraining."\n");
	fwrite($fh,"cp ".$framedp_dir_training."*.mat* ".$framedp_dir_finaltraining."\n");
	fwrite($fh,"rm -rf ".$framedp_dir_training."\n");
	*/
	
	fwrite($fh,"rm -rf ".$framedp_dir_training."000\n");
	fwrite($fh,"rm -rf ".$framedp_dir_training."train000\n");
	fwrite($fh,"rm -rf ".$framedp_dir_training."PARALOOP_error\n");

	//also remove blast output training file and training file itself
	fwrite($fh,"rm -f ".$transcript_seq_file."\n");
	fwrite($fh,"rm -f ".$transcript_seq_file.".refdb.blastx\n");       

	//okay, now we need to update the status of the FrameDP training.
	//this is done again by the Java program, who will check the training directory for the necessary content.
	//finished state, and later on updates the framedp state
       	$java_parameters	= array("check_training_output",TRAPID_DB_SERVER,TRAPID_DB_NAME,TRAPID_DB_USER,TRAPID_DB_PASSWORD,
							$exp_id,$framedp_dir_training);	
	fwrite($fh,"java -cp ".$java_location.".:..:".$java_location."mysql.jar ".$java_program." ".implode(" ",$java_parameters)."\n"); 

	fclose($fh);
	shell_exec("chmod a+x ".$shell_file);
	return $shell_file;
  }





    // Generate a random character string
    function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890'){
	$chars_length = (strlen($chars) - 1);     		// Length of character list
	$string = $chars{rand(0, $chars_length)};    	// Start our string  
	for ($i = 1; $i < $length; $i = strlen($string)){   // Generate random string    
	    $r = $chars{rand(0, $chars_length)};            // Grab a random character from our list
	    if ($r != $string{$i - 1}) $string .=  $r;      // Make sure the same two characters don't appear next to each other
	}   
	return $string;
    }


    function send_registration_email($email,$password,$password_update=false){      
      $message 	        	= "Welcome to the online TRAPID system for rapid analysis of transcriptome data.\nHere is the required authentication information.\n\nUser email-address: ".$email."\nPassword: ".$password."\n\nThank you for using the TRAPID system.";     
      if($password_update){
	$message		= "The password for your TRAPID account has been changed.\n\nThe new password is: ".$password."\n\nThank you for using the TRAPID system\n";
      }
      $this->Email->to 			= $email;
      $this->Email->subject		= "TRAPID authentication information";	
      $this->Email->replyTo		= "no-reply@psb.ugent.be";
      $this->Email->from 		= "TRAPID webmaster <no-reply@psb.ugent.be>";     
      $this->Email->additionalParams	= "-fno-reply@psb.ugent.be";
      $this->Email->send($message);
      $this->Email->reset();
      
      //send email to administrators to be warned when a new user is added
      if(!$password_update){
     	  $this->Email->reset();
    	  $this->Email->to		= "mibel@psb.ugent.be,klpoe@psb.ugent.be";
    	  $this->Email->subject		= "TRAPID new user";
    	  $this->Email->replyTo		= "no-reply@psb.ugent.be";
    	  $this->Email->from 		= "TRAPID webmaster <no-reply@psb.ugent.be>";
    	  $this->Email->additionalParams	= "-fno-reply@psb.ugent.be";
    	  $this->Email->send("New user added to TRAPID system\n\nUser-login: ".$email);
    	  $this->Email->reset();
      }
    }
   
}
?>