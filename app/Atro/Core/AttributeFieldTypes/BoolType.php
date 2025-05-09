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

namespace Atro\Core\AttributeFieldTypes;

use Espo\ORM\IEntity;

class BoolType extends AbstractFieldType
{
    public function convert(IEntity $entity, string $id, string $name, array $row, array &$attributesDefs): void
    {
        $entity->fields[$name] = [
            'type'             => 'bool',
            'name'             => $name,
            'attributeValueId' => $id,
            'column'           => "bool_value",
            'required'         => !empty($row['is_required'])
        ];

        $entity->set($name, $row[$entity->fields[$name]['column']] ?? null);

        if ($entity->get($name) !== null) {
            $entity->set($name, !empty($entity->get($name)));
        }

        $entity->entityDefs['fields'][$name] = [
            'attributeValueId' => $id,
            'type'             => 'bool',
            'required'         => !empty($row['is_required']),
            'notNull'          => !empty($row['not_null']),
            'label'            => $row[$this->prepareKey('name', $row)],
            'tooltip'          => !empty($row[$this->prepareKey('tooltip', $row)]),
            'tooltipText'      => $row[$this->prepareKey('tooltip', $row)]
        ];

        $attributesDefs[$name] = $entity->entityDefs['fields'][$name];
    }
}
