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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot8Dot15 extends Base
{
    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('scheduled_job', 't')
            ->set('deleted', ':true')
            ->where('t.job = :jobName')
            ->andWhere('t.is_internal = :true')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('jobName', 'UpdateCurrencyExchangeViaECB')
            ->executeStatement();
    }

    public function down(): void
    {
    }
}
