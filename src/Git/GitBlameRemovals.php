<?php

namespace Bdlangton\Php\Git;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Git blame removals class.
 *
 * Runs blame on who the last person to change lines that were just removed
 * or changed.
 */
class GitBlameRemovals extends ApplicationBase {

  /**
   * The sha of the commit to compare against.
   *
   * @var string
   */
  protected $sha;

  /**
   * Constructor.
   */
  public function __construct(string $sha = '') {
    $gitRoot = NULL;
    exec("git rev-parse --show-toplevel", $gitRoot);
    $this->gitRoot = $gitRoot[0] ?? '';
    $this->sha = $sha;
    parent::__construct('Git Blame Removals', '1.0.0');
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    // If there is no sha value, then abort.
    if (empty($this->sha)) {
      return;
    }

    $this->input = $input;
    $this->output = $output;

    $files = $this->getChangedFiles();

    // These checks require valid changed files.
    if (!empty($files)) {
      // Git Blame Removals.
      $output->writeln("<fg=white;options=bold;bg=cyan> -- Checking Git Blame Removals -- </fg=white;options=bold;bg=cyan>\n");
      $this->checkBlame($files);
    }
  }

  /**
   * Check blame of files changed since the $sha commit.
   *
   * @param array $files
   *   Array of files to check.
   */
  protected function checkBlame(array $files) {
    $diffs = '';
    $commandLineArgs = [
      'git',
      'diff',
      $this->sha,
      '',
    ];

    foreach ($files as $file) {
      $commandLineArgs[3] = $file;

      // Get lines that have been removed (starting with '- ').
      $gitdiff = new Process($commandLineArgs);
      $gitdiff->run();
      $grep = new Process(['grep', '-E', '^-']);
      $grep->setInput($gitdiff->getOutput());
      $grep->run();
      $sed = new Process(['sed', '-e', 's/^-[ ]*//g']);
      $sed->setInput($grep->getOutput());
      $sed->run();

      // Break the sed results into one entry per line.
      $sed_array = preg_split('/$\R?^/m', $sed->getOutput());
      foreach ($sed_array as $data) {
        // Run a git blame and find the line that matches the removed line.
        $data = trim(preg_replace('/\s\s+/', ' ', $data));
        $quote = '^[^(]*? (\([^)]*\))\s*' . str_replace('\$', '.', preg_quote($data)) . '$';
        $gitdiff = new Process([
          'git', 'blame', '--date=relative',
          $this->sha, '--', $file,
        ]);
        $gitdiff->run();
        $grep = new Process(['grep', '-E', $quote]);
        $grep->setInput($gitdiff->getOutput());
        $grep->run();
        $diffs .= $grep->getOutput();
      }
    }

    if (!empty($diffs)) {
      $this->output->writeln(sprintf('<fg=yellow>%s</fg=yellow>', trim($diffs)));
      $this->output->writeln('');
    }
  }

  /**
   * Get a list of files that have changed in this commit.
   *
   * @return array
   *   Return an array of files that have been changed.
   */
  protected function getChangedFiles() {
    $files = [];

    exec("git diff-index --name-only $(git merge-base HEAD $this->sha)", $files);

    foreach ($files as &$file) {
      $file = $this->gitRoot . '/' . $file;
    }

    return array_filter($files, [$this, 'verifyFile']);
  }

}
