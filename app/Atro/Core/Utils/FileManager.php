<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Espo\Core\Utils\File\Manager;

class FileManager extends Manager
{
    public function __construct(Container $container)
    {
        parent::__construct($container->get('config'));
    }

    public function getFileDir(string $path): string
    {
        $dirPath = explode(DIRECTORY_SEPARATOR, $path);
        array_pop($dirPath);

        return implode(DIRECTORY_SEPARATOR, $dirPath);
    }

    public function scanDir(string $dir): array
    {
        // prepare result
        $result = [];

        if (file_exists($dir) && is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if (!in_array($item, ['.', '..'])) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    public function removeAllInDir(string $dir): void
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach ($this->scanDir($dir) as $object) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                    $this->removeAllInDir($dir . DIRECTORY_SEPARATOR . $object);
                } else {
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
}
