<?php

function AAO_init() {
  $init = array(
    "wget" => array(
      "type" => "cmd",
      "required" => "required",
      "missing text" => "AAO requires wget to access data.",
      "version flag" => "--version"
    )
  );
  return($init);
}

function AAO_prepare() {
  global $system;
  $system["data"]["aao"] = array();
  core_log("info", "aao", "Attempting to list recording sources on analysis server.");
  exec("s3cmd get --force s3://bioacoustica-analysis/AAO/species_data_sources.txt modules/traits-AAO/AAO/species_data_sources.txt", $output, $return_value);
  if ($return_value == 0) {
    $keys = array(
      "taxon",
      "accepted taxon",
      "external repository",
      "source",
      "count",
      "unit name 1",
      "unit name 2",
      "unit name 3",
      "unit name 4",
      "rank"
    );
    $fh_recordings = fopen("modules/traits-AAO/AAO/species_data_sources.txt", 'r');
    core_log("info", "aao", "Downloaded source metadata to modules/traits-AAO/AAO/species_data_sources.txt");
    while (($data = fgetcsv($fh_recordings)) !== FALSE) {
      $system["data"]["aao"][] = array_combine($keys, $data);
    }
  } else {
    core_log("fatal", "aao", "Could not download source recording metdata.");
  }
  
  $fh_tree = fopen("modules/traits-AAO/AAO/taxa_list.txt", 'r');
  while (($data = fgetcsv($fh_tree)) !== FALSE) {
      $system["data"]["aao_tree"][$data[0]] = array();
  }
  
  return(array());
}

function AAO_consolidate() {
  global $system;

  $rec_taxa = array();
  foreach ($system["data"]["aao"] as $key => $data) {
    if ($data["source"] == "External Repository Copies") {
      if ($data["external repository"] != "") {
        $system["data"]["aao"][$key]["source"] = $data["external repository"];
      }
    }
    unset($system["data"]["aao"][$key]["external repository"]);
    $tree_taxon = "";
    $tree_match = "";
    if (!in_array($data["rank"], array("Species", "Subspecies"))) {
      continue;
    }
    if ($data["rank"] == "Species") {
      $tree_match = "Species";
      if ($data["unit name 3"] == "") {
        $tree_taxon = $data["unit name 1"]."_".$data["unit name 2"];
      }
      if ($data["unit name 3"] != "") {
        $tree_taxon = $data["unit name 1"]."_".$data["unit name 3"];
      }
    }
    if ($data["rank"] == "Subspecies") {
      $tree_match = "Subspecies";
      if ($data["unit name 4"] == "") {
        $tree_taxon = $data["unit name 1"]."_".$data["unit name 2"];
      }
      if ($data["unit name 4"] != "") {
        $tree_taxon = $data["unit name 1"]."_".$data["unit name 3"];
      }
    }
    $system["data"]["aao"][$key]["tree name"] = $tree_taxon;
    $system["data"]["aao"][$key]["tree match"] = $tree_match;
    $rec_taxa[$system["data"]["aao"][$key]["tree name"]][] = $system["data"]["aao"][$key];
  }
  
  $rec_matches = array();
  foreach ($system["data"]["aao_tree"] as $tree_name => $data) {
    if (array_key_exists($tree_name, $rec_taxa)) {
      $system["data"]["aao_tree"][$tree_name]["recording"] = $rec_taxa[$tree_name];
    } else {
      $system["data"]["aao_tree"][$tree_name]["recording"] = NULL;
    }
  }
  
  $fh_rec_matches = fopen("modules/traits-AAO/AAO/recording-matches.csv", "w");
  
  $columns = array(
      "tree taxon",
      "BA listed taxon",
      "BA accepted taxon",
      "Recording source",
      "Recording count",
      "unit name 1",
      "unit name 2",
      "unit name 3",
      "unit name 4",
      "rank"
  );
  
  fputcsv($fh_rec_matches, $columns);
  
  foreach ($system["data"]["aao_tree"] as $name => $data) {
    if (!is_null($data["recording"])) {
      foreach ($data["recording"] as $num => $recording) {
        $line = array_merge(array($name), $recording);
        fputcsv($fh_rec_matches, $line);
      }
    } else {
      $line = array($name);
      fputcsv($fh_rec_matches, $line);
    }
  }
  fclose($fh_rec_matches);
  
  $return = array(
    "rec_matches" => array(
      "file name" => "recording-matches.csv",
      "local path" => "modules/traits-AAO/AAO/",
      "save path" => "AAO/"
    )
  );
  return($return);
}