<?php 
if(isset($error)){
	echo "<span class='error'>".$error."</span><br/>\n"; 
}
else if(!isset($result)){
	echo "<span class='error'>Undefined error: no output data</span><br/>\n";
}
else if(count($result)==0){
	echo "<span class='message'>No enriched terms found for this subset</span><br/>\n";	
}
else{	

/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	if($type=="go"){	
	//CHARTS	
	echo "<div style='margin-bottom:20px;'>\n";
	echo "<h4>GO enrichment charts</h4>\n";
	echo "<div style='width:860px;background-color:white;padding:20px;margin-top:10px;border:1px solid black;'>";
	$go_types_titles	= array("MF"=>"Molecular Function","CC"=>"Cellular Component","BP"=>"Biological Process");	
	foreach($go_types as $go_type=>$gos){
		$data_chart	= array("GO enrichment"=>array("color"=>"#123987","font_size"=>11,"graph_style"=>"bar"),
					"p-value"=>array("color"=>"#789321","font_size"=>11,"graph_style"=>"lines")
					);		
	
		$enrich_data	= array();
		$pval_data	= array();
		$links		= array();
		$tips		= array();
		$labels		= array();
		$max_val 	= 0;
		$min_val 	= 0;
		$max_p_val 	= 5;
		foreach($gos as $counter=>$go_id){
			$web_go_id	= str_replace(":","-",$go_id);
			$desc		= $go_descriptions[$go_id][0];
			$res		= $result[$go_id];
			if(!$res['is_hidden']){
				$val		= number_format($res['enrichment'],2);	
				$enrich_data[]	= $val;
				$split_p_value  = explode("E",$res["p-value"]);		
				$p_value	= 0;
				if(count($split_p_value)==2){
		    			if(substr($split_p_value[1],0,1)=='-'){$p_value = substr($split_p_value[1],1);}	
				}
				else{
		    			$p_t = $res['p-value'] / 1000.0 ;
		   		 	$split_p_value = explode("E",$p_t);
		    			if(count($split_p_value)==2){
						if(substr($split_p_value[1],0,1)=='-'){
			    				$p_value = substr($split_p_value[1],1);
			    				$p_value = $p_value - 3;
						}
		    			}
				}			
				$pval_data[]	= $p_value;
				if($val>$max_val){$max_val = $val;}
				if($val<$min_val){$min_val = $val;}
				if($p_value > $max_p_val){$max_p_val = $p_value;}
			
				$tips[]		= str_replace(",",";",$desc);
				$links[]	= $html->url(array("controller"=>"functional_annotation","action"=>"go",$exp_id,$web_go_id)
								,true);
				$labels[]	= $go_id;
			}
		}
		//attach extra empty data in case less than 4 GO's are present.
		if(count($enrich_data)<4){
			for($i=0;$i<=(4-count($enrich_data));$i++){
				$enrich_data[] 	= 0; 
				$labels[] 	= ""; 
				$tips[]	 	= ""; 
				$links[] 	= ""; 
				$pval_data[] 	= -0.001;	
			}
		}

		array_multisort($pval_data,SORT_DESC,$enrich_data,$labels,$tips,$links);
		$max_p_val = intval($max_p_val) + 2;

		$data_chart["GO enrichment"]["data"]		= $enrich_data;
		$data_chart["GO enrichment"]["links"]		= $links;
		$data_chart["GO enrichment"]["tips"]		= $tips;
		$data_chart["p-value"]["data"]			= $pval_data;
		$data_chart["p-value"]["links"]			= $links;
		$data_chart["p-value"]["tips"]			= $tips;
		
		echo "<center><span style='color:#153E7E;text-decoration:underline;'>".$go_types_titles[$go_type]."</span></center><br/><br/>";
		echo "<center>";
		$flashChart->begin(750,250,"GO terms","Log2 enrichment",false);
		if($min_val==0){$min_val = 1;}
		$flashChart->setRange("y",intval($min_val)-1,intval($max_val)+1);
		$flashChart->labels($labels);
		$orientation = 0;
		if(count($enrich_data) > 8){$orientation = 1;}	
		$flashChart->configureGrid(array("x_axis"=>array("size"=>8,"orientation"=>$orientation,"legend"=>"GO terms")));
		$flashChart->setGridColor("#666666","ffffff");				
		$flashChart->setInnerBackground("#F0F0F0","#D2D2D2",315);
		$flashChart->setData($data_chart);
		$flashChart->createRightYAxis($max_p_val,"-log(p-value)","#789321");
		$flashChart->addToRightYAxis(2);		
		$flashChart->setNewToolTip("#x_label#<br>#tip#<br>#val#");	  	
		echo $flashChart->render();		
		echo $html->link("View GO enrichment graph",array("controller"=>"tools","action"=>"go_enrichment_graph",$exp_id,$subset,$go_type,$selected_pvalue));	
		echo "</center>\n";
		if($go_type!="CC"){
			echo "<br/><br/>\n";		
			echo "<hr/>\n";
			echo "<br/><br/>\n";
		}
	}			

	echo "</div>\n";
	echo "</div>\n";
	


	//TABLE 
	echo "<div>\n";
	echo "<h4>GO enrichment data table</h4>\n";
	echo "<table cellpadding='0' cellspacing='0' style='width:900px;'>\n";
	echo "<tr>";
	echo "<th style='width:5%'>GO type</th>";
	echo "<th style='width:10%'>GO term</th>";
	echo "<th style='width:10%'>Enrichment</th>";
	echo "<th style='width:10%'>p-value</th>";
	echo "<th style='width:10%'>Subset ratio</th>";
	echo "<th style='width:50%'>Description</th>";
	echo "<th style='width:5%'>Shown</th>";
	echo "</tr>\n";
	$i	= 0;
	foreach($go_types as $go_type=>$gos){
		foreach($gos as $counter=>$go_id){
			$class		= null;
			$lower_row	= null;
			if($i++%2==0){$class=" class='altrow2' ";}
			if($counter==(count($gos)-1)){$lower_row=" style='border-bottom:1px solid black;' ";}
			$go_web_id	= str_replace(":","-",$go_id);
			$res		= $result[$go_id];
			echo "<tr $class>";
			echo "<td $lower_row>".$go_type."</td>";
			echo "<td $lower_row>".$html->link($go_id,array("controller"=>"functional_annotation","action"=>"go",$exp_id,$go_web_id))."</td>";
			echo "<td $lower_row>".number_format($res['enrichment'],2)."</td>";

			$split_p_value 	= explode("E",$res["p-value"]);
			$p_value	= null;
		    	if(count($split_p_value)==1){
				$p_value = number_format($res["p-value"],2);
				if($p_value==0.00){$p_value=number_format($res["p-value"],4);}
		    	}
		    	else{
				$p_value	= number_format($split_p_value[0],2)."E".$split_p_value[1];		
		    	}	

			echo "<td $lower_row>".$p_value."</td>";
			echo "<td $lower_row>".$html->link(number_format($res["subset_ratio"],2)."%",array("controller"=>"trapid","action"=>"transcript_selection",$exp_id,"go",$go_web_id,"label",$subset))."</td>";
			echo "<td $lower_row>".$go_descriptions[$go_id][0]."</td>";
			if($res['is_hidden']){echo "<td $lower_row><span class='error'>X</span></td>";}
			else{echo "<td $lower_row><span class='message'>V</span></td>";}			
			echo "</tr>\n";
		}
	}				
	echo "</table>\n";
	echo "</div>\n";
	}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	else if($type=="ipr"){
		//CHARTS	
		echo "<div style='margin-bottom:20px;'>\n";
		echo "<h4>Protein domain enrichment chart</h4>\n";
		echo "<div style='width:860px;background-color:white;padding:20px;margin-top:10px;border:1px solid black;'>";


		$data_chart	= array("Protein domain enrichment"=>array("color"=>"#123987","font_size"=>11,"graph_style"=>"bar"),
					"p-value"=>array("color"=>"#789321","font_size"=>11,"graph_style"=>"lines")
					);		
	
		$enrich_data	= array();
		$pval_data	= array();
		$links		= array();
		$tips		= array();
		$labels		= array();
		$max_val 	= 0;
		$min_val 	= 0;
		$max_p_val 	= 5;
		foreach($result as $ipr=>$res){	
			$desc		= $ipr_descriptions[$ipr][0];	
			if(!$res['is_hidden']){
				$val		= number_format($res['enrichment'],2);	
				$enrich_data[]	= $val;
				$split_p_value  = explode("E",$res["p-value"]);		
				$p_value	= 0;
				if(count($split_p_value)==2){
		    			if(substr($split_p_value[1],0,1)=='-'){$p_value = substr($split_p_value[1],1);}	
				}
				else{
		    			$p_t = $res['p-value'] / 1000.0 ;
		   		 	$split_p_value = explode("E",$p_t);
		    			if(count($split_p_value)==2){
						if(substr($split_p_value[1],0,1)=='-'){
			    				$p_value = substr($split_p_value[1],1);
			    				$p_value = $p_value - 3;
						}
		    			}
				}			
				$pval_data[]	= $p_value;
				if($val>$max_val){$max_val = $val;}
				if($val<$min_val){$min_val = $val;}
				if($p_value > $max_p_val){$max_p_val = $p_value;}
			
				$tips[]		= str_replace(",",";",$desc);
				$links[]	= $html->url(array("controller"=>"functional_annotation","action"=>"interpro",$exp_id,$ipr)
								,true);
				$labels[]	= $ipr;
			}
		}
		//attach extra empty data in case less than 4 GO's are present.
		if(count($enrich_data)<4){
			for($i=0;$i<=(4-count($enrich_data));$i++){
				$enrich_data[] 	= 0; 
				$labels[] 	= ""; 
				$tips[]	 	= ""; 
				$links[] 	= ""; 
				$pval_data[] 	= -0.001;	
			}
		}

		array_multisort($pval_data,SORT_DESC,$enrich_data,$labels,$tips,$links);
		$max_p_val = intval($max_p_val) + 2;

		$data_chart["Protein domain enrichment"]["data"]		= $enrich_data;
		$data_chart["Protein domain enrichment"]["links"]		= $links;
		$data_chart["Protein domain enrichment"]["tips"]		= $tips;
		$data_chart["p-value"]["data"]			= $pval_data;
		$data_chart["p-value"]["links"]			= $links;
		$data_chart["p-value"]["tips"]			= $tips;
			
		echo "<center>";
		$flashChart->begin(750,250,"Protein domains","Log2 enrichment",false);
		if($min_val==0){$min_val = 1;}
		$flashChart->setRange("y",intval($min_val)-1,intval($max_val)+1);
		$flashChart->labels($labels);
		$orientation = 0;
		if(count($enrich_data) > 8){$orientation = 1;}	
		$flashChart->configureGrid(array("x_axis"=>array("size"=>8,"orientation"=>$orientation,"legend"=>"Protein domains")));
		$flashChart->setGridColor("#666666","ffffff");				
		$flashChart->setInnerBackground("#F0F0F0","#D2D2D2",315);
		$flashChart->setData($data_chart);
		$flashChart->createRightYAxis($max_p_val,"-log(p-value)","#789321");
		$flashChart->addToRightYAxis(2);		
		$flashChart->setNewToolTip("#x_label#<br>#tip#<br>#val#");	  	
		echo $flashChart->render();			
		echo "</center>\n";	
				

		echo "</div>\n";
		echo "</div>\n";
	


		//TABLE 
		echo "<div>\n";
		echo "<h4>Protein domain enrichment data table</h4>\n";
		echo "<table cellpadding='0' cellspacing='0' style='width:900px;'>\n";
		echo "<tr>";	
		echo "<th style='width:10%'>Protein domain</th>";
		echo "<th style='width:10%'>Enrichment</th>";
		echo "<th style='width:10%'>p-value</th>";
		echo "<th style='width:10%'>Subset ratio</th>";
		echo "<th style='width:50%'>Description</th>";
		echo "<th style='width:5%'>Shown</th>";
		echo "</tr>\n";
		$i	= 0;
	
		foreach($result as $ipr=>$res){
			$class		= null;
			$lower_row	= null;
			if($i++%2==0){$class=" class='altrow2' ";}
			echo "<tr $class>";		
			echo "<td $lower_row>".$html->link($ipr,array("controller"=>"functional_annotation","action"=>"interpro",$exp_id,$ipr))."</td>";
			echo "<td $lower_row>".number_format($res['enrichment'],2)."</td>";

			$split_p_value 	= explode("E",$res["p-value"]);
			$p_value	= null;
		    	if(count($split_p_value)==1){
				$p_value = number_format($res["p-value"],2);
				if($p_value==0.00){$p_value=number_format($res["p-value"],4);}
		    	}
		    	else{
				$p_value	= number_format($split_p_value[0],2)."E".$split_p_value[1];		
		    	}	

			echo "<td $lower_row>".$p_value."</td>";
			echo "<td $lower_row>".$html->link(number_format($res["subset_ratio"],2)."%",array("controller"=>"trapid","action"=>"transcript_selection",$exp_id,"interpro",$ipr,"label",$subset))."</td>";
			echo "<td $lower_row>".$ipr_descriptions[$ipr][0]."</td>";
			if($res['is_hidden']){echo "<td $lower_row><span class='error'>X</span></td>";}
			else{echo "<td $lower_row><span class='message'>V</span></td>";}			
			echo "</tr>\n";
		}
					
		echo "</table>\n";
		echo "</div>\n";
	



	}

}
?>
