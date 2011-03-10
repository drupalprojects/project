<?php
// $Id$

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
    // Load options.
    $options = $this->currentSample->options;

    // Figure out the date range we're using for this sample.
    if ($previous = $this->getPreviousSample()) {
      $start = $previous->timestamp;
      $end = $this->currentSample->timestamp;
    }
    else {
      return FALSE;
    }

    $args = array($start, $end);

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
    $nodes = db_query("SELECT np.nid, COUNT(nr.nid) AS count FROM {node} np LEFT JOIN {project_release_nodes} prn ON np.nid = prn.pid LEFT JOIN {node} nr ON prn.nid = nr.nid AND nr.created > %d AND nr.created < %d$where GROUP BY np.nid ORDER BY NULL", $args);
    while ($node = db_fetch_object($nodes)) {
      $this->currentSample->values[$node->nid] = array($node->count);
    }
  }

}
