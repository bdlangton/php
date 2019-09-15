<?php

namespace Bdlangton\Php\Finder;

use Nette\Utils\Finder;
use Symplify\EasyCodingStandard\Contract\Finder\CustomSourceProviderInterface;

/**
 * PHP Files Provider class.
 */
final class PhpFilesProvider implements CustomSourceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function find(array $source) {
    return Finder::find('*.php', '*.module')->in($source);
  }

}
