<?php

/**
 * Render a table of Differential revisions.
 */
final class DifferentialRevisionListView extends AphrontView {

  private $revisions = array();
  private $header;
  private $noDataString;
  private $noBox;
  private $background = null;
  private $unlandedDependencies = array();

  public function setUnlandedDependencies(array $unlanded_dependencies) {
    $this->unlandedDependencies = $unlanded_dependencies;
    return $this;
  }

  public function getUnlandedDependencies() {
    return $this->unlandedDependencies;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $this->revisions = $revisions;
    return $this;
  }

  public function setNoBox($box) {
    $this->noBox = $box;
    return $this;
  }

  public function setBackground($background) {
    $this->background = $background;
    return $this;
  }

  public function render() {
    $viewer = $this->getViewer();

    $this->initBehavior('phabricator-tooltips', array());
    $this->requireResource('aphront-tooltip-css');

    $reviewer_limit = 7;

    $reviewer_phids = array();
    $reviewer_more = array();
    $handle_phids = array();
    foreach ($this->revisions as $key => $revision) {
      $reviewers = $revision->getReviewers();
      if (count($reviewers) > $reviewer_limit) {
        $reviewers = array_slice($reviewers, 0, $reviewer_limit);
        $reviewer_more[$key] = true;
      } else {
        $reviewer_more[$key] = false;
      }

      $phids = mpull($reviewers, 'getReviewerPHID');

      $reviewer_phids[$key] = $phids;
      foreach ($phids as $phid) {
        $handle_phids[$phid] = $phid;
      }

      $author_phid = $revision->getAuthorPHID();
      $handle_phids[$author_phid] = $author_phid;
    }

    $handles = $viewer->loadHandles($handle_phids);

    $list = new PHUIObjectItemListView();
    foreach ($this->revisions as $key => $revision) {
      $item = id(new PHUIObjectItemView())
        ->setViewer($viewer);

      $icons = array();

      $phid = $revision->getPHID();
      $flag = $revision->getFlag($viewer);
      if ($flag) {
        $flag_class = PhabricatorFlagColor::getCSSClass($flag->getColor());
        $icons['flag'] = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$flag_class,
          ),
          '');
      }

      $modified = $revision->getDateModified();

      if (isset($icons['flag'])) {
        $item->addHeadIcon($icons['flag']);
      }

      $item->setObjectName($revision->getMonogram());
      $item->setHeader($revision->getTitle());
      $item->setHref($revision->getURI());

      if ($revision->getHasDraft($viewer)) {
        $draft = id(new PHUIIconView())
          ->setIcon('fa-comment yellow')
          ->addSigil('has-tooltip')
          ->setMetadata(
            array(
              'tip' => pht('Unsubmitted Comments'),
            ));
        $item->addAttribute($draft);
      }

      $author_handle = $handles[$revision->getAuthorPHID()];
      $item->addByline(pht('Author: %s', $author_handle->renderLink()));

      $unlanded = idx($this->unlandedDependencies, $phid);
      if ($unlanded) {
        $item->addAttribute(
          array(
            id(new PHUIIconView())->setIcon('fa-chain-broken', 'red'),
            ' ',
            pht('Open Dependencies'),
          ));
      }

      $more = null;
      if ($reviewer_more[$key]) {
        $more = pht(', ...');
      } else {
        $more = null;
      }

      if ($reviewer_phids[$key]) {
        $item->addAttribute(
          array(
            pht('Reviewers:'),
            ' ',
            $viewer->renderHandleList($reviewer_phids[$key])
              ->setAsInline(true),
            $more,
          ));
      } else {
        $item->addAttribute(phutil_tag('em', array(), pht('No Reviewers')));
      }

      $item->setEpoch($revision->getDateModified());

      if ($revision->isClosed()) {
        $item->setDisabled(true);
      }

      $icon = $revision->getStatusIcon();
      $color = $revision->getStatusIconColor();

      $item->setStatusIcon(
        "{$icon} {$color}",
        $revision->getStatusDisplayName());

      $list->addItem($item);
    }

    $list->setNoDataString($this->noDataString);


    if ($this->header && !$this->noBox) {
      $list->setFlush(true);
      $list = id(new PHUIObjectBoxView())
        ->setBackground($this->background)
        ->setObjectList($list);

      if ($this->header instanceof PHUIHeaderView) {
        $list->setHeader($this->header);
      } else {
        $list->setHeaderText($this->header);
      }
    } else {
      $list->setHeader($this->header);
    }

    return $list;
  }

}
