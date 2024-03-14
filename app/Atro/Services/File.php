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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class File extends Base
{
    protected $mandatorySelectAttributeList = ['storageId', 'path', 'thumbnailsPath'];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $fileNameParts = explode('.', $entity->get('name'));

        $entity->set('extension', strtolower(array_pop($fileNameParts)));
        $entity->set('downloadUrl', $entity->getDownloadUrl());
        $entity->set('smallThumbnailUrl', $entity->getSmallThumbnailUrl());
        $entity->set('mediumThumbnailUrl', $entity->getMediumThumbnailUrl());
        $entity->set('largeThumbnailUrl', $entity->getLargeThumbnailUrl());
    }

    public function createEntity($attachment)
    {
        if (!property_exists($attachment, 'piecesCount')) {
            return parent::createEntity($attachment);
        }

        $storageId = $attachment->storageId ?? null;
        if (empty($storageId) || empty($storageEntity = $this->getEntityManager()->getRepository('Storage')->get($storageId))) {
            throw new BadRequest(
                sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions'), $this->getInjection('language')->translate('storage', 'fields', 'File'))
            );
        }

        /** @var FileStorageInterface $storage */
        $storage = $this->getInjection('container')->get($storageEntity->get('type') . 'Storage');

        $chunks = $storage->createChunk($attachment);
        if (count($chunks) === $attachment->piecesCount) {
            $attachment->allChunks = $chunks;
            return parent::createEntity($attachment);
        }

        throw new Error();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
