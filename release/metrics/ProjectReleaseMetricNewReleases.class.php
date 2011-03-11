<?php

/**
 * @file
 * Class for new_releases metric.
 */

class ProjectReleaseMetricNewReleases extends SamplerMetric {

  /**
   * Counts new releases made for each project during any given sample period.
   *
   * @return
   *   An associative array keyed by project nid, where the value is an array
   *   containing the number of new releases created for that project in the
   *   current sample period.
   */
  public function computeSample() {

    $sample = $this->currentSample;
    $options = $sample->options;
    $args = array($sample->sample_startstamp, $sample->sample_endstamp);

    // Restrict to only the passed project nids.
    if (!empty($options['object_ids'])) {
      $where = " WHERE prn.pid IN (". db_placeholders($options['object_ids']) .")";
      $args = array_merge($args, $options['object_ids']);
    }
    else {
      $where = '';
    }

    // We pull all project nodes from {node}, LEFT JOIN with release nodes
    // from {project_release_nodes} to get releases per project, LEFT JOIN
    // with {node} each release node to filter by release node creation date,
    // and then GROUP by the project nid so we can get release counts.
    $nodes = db_query("SELECT pp.nid, COUNT(nr.nid) as count FROM {project_projects} pp LEFT JOIN {project_release_nodes} prn ON pp.nid = prn.pid LEFT JOIN {node} nr ON prn.nid = nr.nid AND nr.type = 'project_release' AND nr.created >= %d AND nr.created < %d$where GROUP BY pp.nid", $args);
    while ($node = db_fetch_object($nodes)) {
      $this->currentSample->values[$node->nid]['releases'] = $node->count;
    }
  }

  public function trackObjectIDs() {
    $nids = array();
    $releases = db_query("SELECT nid FROM {project_projects}");
    while ($release = db_fetch_object($releases)) {
      $nids[] = $release->nid;
    }
    return $nids;
  }
}

