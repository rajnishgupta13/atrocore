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

use Atro\Core\Templates\Repositories\Base;
use Espo\Core\DataManager;
use Espo\ORM\Entity;

class Language extends Base
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->refreshTimestamp($options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->refreshTimestamp($options);
    }

    protected function refreshTimestamp(array $options): void
    {
        if (!empty($options['keepCache'])) {
            return;
        }

        $this->getInjection('language')->clearCache();

        $this->getConfig()->set('cacheTimestamp', time());
        $this->getConfig()->save();
        DataManager::pushPublicData('dataTimestamp', time());
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
