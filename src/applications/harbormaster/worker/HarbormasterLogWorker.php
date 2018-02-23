<?php

final class HarbormasterLogWorker extends HarbormasterWorker {

  protected function doWork() {
    $viewer = $this->getViewer();

    $data = $this->getTaskData();
    $log_phid = idx($data, 'logPHID');

    $log = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($log_phid))
      ->executeOne();
    if (!$log) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Invalid build log PHID "%s".',
          $log_phid));
    }

    $lock = $log->getLock();

    try {
      $lock->lock();
    } catch (PhutilLockException $ex) {
      throw new PhabricatorWorkerYieldException(15);
    }

    $caught = null;
    try {
      $log->reload();

      if ($log->getLive()) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Log "%s" is still live. Logs can not be finalized until '.
            'they have closed.',
            $log_phid));
      }

      $this->finalizeBuildLog($log);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $lock->unlock();

    if ($caught) {
      throw $caught;
    }
  }

  private function finalizeBuildLog(HarbormasterBuildLog $log) {
    $viewer = $this->getViewer();

    $data = $this->getTaskData();
    $is_force = idx($data, 'force');

    if (!$log->getByteLength() || $is_force) {
      $iterator = $log->newChunkIterator()
        ->setAsString(true);

      $byte_length = 0;
      foreach ($iterator as $block) {
        $byte_length += strlen($block);
      }
      $log
        ->setByteLength($byte_length)
        ->save();
    }

    if ($log->canCompressLog()) {
      $log->compressLog();
    }

    if ($is_force) {
      $log->destroyFile();
    }

    if (!$log->getFilePHID()) {
      $iterator = $log->newChunkIterator()
        ->setAsString(true);

      $source = id(new PhabricatorIteratorFileUploadSource())
        ->setName('harbormaster-log-'.$log->getID().'.log')
        ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
        ->setMIMEType('application/octet-stream')
        ->setIterator($iterator);

      $file = $source->uploadFile();

      $file->attachToObject($log->getPHID());

      $log
        ->setFilePHID($file->getPHID())
        ->save();
    }

  }

}
