<?php

/**
 * The Task entity class.
 */
interface GDPRTaskInterface extends EntityInterface {

  /**
   * Gets the human readable label of the tasks bundle.
   */
  public function bundleLabel();

}