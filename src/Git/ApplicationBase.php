<?php

namespace Bdlangton\Php\Git;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Pre-commit class to handle all pre-commit hooks.
 */
abstract class ApplicationBase extends Application {

  /**
   * The Symfony output interface.
   *
   * @var Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * The Symfony input interface.
   *
   * @var Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * The directory of the project root (the git hooks directory).
   *
   * @var string
   */
  protected $projectRoot;

  /**
   * The root directory of the git repo.
   *
   * @var string
   */
  protected $gitRoot;

  /**
   * File extensions to check.
   *
   * If a modified file doesn't contain one of these extensions, then it will
   * be skipped.
   *
   * @var array
   */
  protected $fileExtensions;

  /**
   * PHP File extensions.
   *
   * Only files ending with these extensions will be checked by PHP checks.
   *
   * @var array
   */
  protected $phpFileExtensions;

  /**
   * Ignore filenames that contain these strings.
   *
   * @var array
   */
  protected $ignoreFilenameStrings;

  /**
   * Ignore file paths that contain these strings.
   *
   * @var array
   */
  protected $ignoreFilePathStrings;

  /**
   * Constructor.
   */
  public function __construct() {
    parent::__construct('Application Base', '1.0.0');
  }

  /**
   * Get a list of files that have changed in this commit.
   *
   * @return array
   *   Return an array of files that have been changed.
   */
  protected function getChangedFiles() {
    $files = [];
    $rev = [];
    $return = 0;

    exec('git rev-parse --verify HEAD 2> /dev/null', $rev, $return);
    $against = $return == 0 ? 'HEAD' : '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
    exec("git diff-index --cached --name-only {$against}", $files);

    foreach ($files as &$file) {
      $file = $this->gitRoot . '/' . $file;
    }

    return array_filter($files, [$this, 'verifyFile']);
  }

  /**
   * Check if the file should be checked or ignored.
   *
   * @param string $file
   *   The file to check.
   *
   * @return bool
   *   Return TRUE if the file should be checked, FALSE if ignored.
   */
  protected function verifyFile($file) {
    // Skip files that don't exist.
    if (!file_exists($file)) {
      return FALSE;
    }

    // Get the filename and extension.
    $filename = pathinfo($file, PATHINFO_BASENAME);
    $ext = pathinfo($file, PATHINFO_EXTENSION);

    // Skip over the file if it matches an ignored filename or an ignored file
    // path, or does not match one of the included file extensions.
    $ignore_filenames = array_filter($this->ignoreFilenameStrings, function ($item) use ($filename) {
      return strpos($filename, $item) !== FALSE;
    });
    $ignore_file_paths = array_filter($this->ignoreFilePathStrings, function ($item) use ($file) {
      return strpos($file, $item) !== FALSE;
    });
    if (
      (!empty($this->fileExtensions) && !in_array($ext, $this->fileExtensions))
      || !empty($ignore_filenames)
      || !empty($ignore_file_paths)
    ) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Check if the file is a PHP file based on extension.
   *
   * @param string $file
   *   The file to check.
   *
   * @return bool
   *   Return TRUE if the file is a PHP file, FALSE if not.
   */
  protected function verifyPhpFile($file) {
    // Skip files that don't exist.
    if (!file_exists($file)) {
      return FALSE;
    }

    // Get the filename extension.
    $ext = pathinfo($file, PATHINFO_EXTENSION);

    // Skip over the file if it does not match one of the included PHP file
    // extensions.
    if (!empty($this->phpFileExtensions) && !in_array($ext, $this->phpFileExtensions)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get the project type of the current git project.
   *
   * Detects the project type by the existence of specific files.
   *
   * @return string
   *   Return the programming language name of the project, NULL if it couldn't
   *   determine it.
   */
  protected function getProjectType() {
    $return = [];

    exec('git rev-parse --show-toplevel', $return);

    if (!empty($return[0])) {
      $base_dir = $return[0];

      if (
        file_exists($base_dir . '/composer.json') ||
        file_exists($base_dir . '/web/composer.json') ||
        file_exists($base_dir . '/docroot/composer.json')
      ) {
        return 'php';
      }
      elseif (
        file_exists($base_dir . '/Gemfile') ||
        file_exists($base_dir . '/web/Gemfile') ||
        file_exists($base_dir . '/docroot/Gemfile')
      ) {
        return 'ruby';
      }
      elseif (file_exists($base_dir . '/package.json')) {
        return 'node';
      }
      elseif (file_exists($base_dir . '/docker-composer.yml')) {
        return 'docker';
      }
    }

    return NULL;
  }

}
