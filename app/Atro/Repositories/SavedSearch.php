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

namespace Atro\Repositories;

use Atro\Core\DataManager;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class SavedSearch extends Base
{
    const CACHE_NAME = 'saved_search';

    public function getEntitiesFromCache()
    {
        $cachedData = $this->getDataManager()->getCacheData(self::CACHE_NAME);
        if ($cachedData === null) {
            $cachedData = $this->find()->toArray();
            $this->getDataManager()->setCacheData(self::CACHE_NAME, $cachedData);
        }
        return $cachedData;
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $entity->set('userId', $this->getEntityManager()->getUser()->id);
        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);
        $this->deleteCacheFile();
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);
        $this->deleteCacheFile();
    }

    protected function deleteCacheFile(): void
    {
        $file = DataManager::CACHE_DIR_PATH . '/' . self::CACHE_NAME . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
    }
}
