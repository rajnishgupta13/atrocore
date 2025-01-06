<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ActionSetLinker extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!empty($setId = $entity->get('setId'))) {
            $actionSet = $this->getEntityManager()->getRepository('Action')->get($setId);
            if (empty($actionSet) || $actionSet->get('type') !== 'set') {
                throw new BadRequest('Action Set should be chosen.');
            }
        }

        $action = $this->getEntityManager()->getRepository('Action')->get($entity->get('actionId'));
        if (empty($action) || $action->get('type') === 'set') {
            throw new BadRequest("Action Set shouldn't be chosen.");
        }

        if ($entity->isNew()) {
            $lastOrder = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('MAX(sort_order)')
                ->from('action_set_linker')
                ->where('set_id=:id')
                ->andWhere('deleted=:false')
                ->setParameter('id', $setId)
                ->setParameter('false', 'false')
                ->fetchOne();

            if (empty($lastOrder)) {
                $lastOrder = 0;
            }

            $entity->set('sortOrder', $lastOrder + 1);
        }

        parent::beforeSave($entity, $options);
    }
}
