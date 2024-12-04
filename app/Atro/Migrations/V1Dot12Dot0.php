<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Console\Cron;
use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot12Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-04 18:00:00');
    }

    public function up(): void
    {
//        file_put_contents(Cron::DAEMON_KILLER, '1');
//
//        $this->getConfig()->set('workersCount', $this->getConfig()->get('queueManagerWorkersCount', 4));
//        $this->getConfig()->save();

        if ($this->isPgSQL()) {
            // ALTER TABLE job ADD priority DOUBLE PRECISION DEFAULT '100';
            // ALTER TABLE job ADD handler VARCHAR(255) DEFAULT NULL;
            // ALTER TABLE job ADD payload TEXT DEFAULT NULL;
            // COMMENT ON COLUMN job.payload IS '(DC2Type:jsonObject)'

            // ALTER TABLE job ADD owner_user_id VARCHAR(36) DEFAULT NULL;
            // ALTER TABLE job ADD assigned_user_id VARCHAR(36) DEFAULT NULL;
            // CREATE INDEX IDX_JOB_OWNER_USER_ID ON job (owner_user_id, deleted);
            // CREATE INDEX IDX_JOB_ASSIGNED_USER_ID ON job (assigned_user_id, deleted)

            // ALTER TABLE scheduled_job ADD type VARCHAR(255) DEFAULT NULL
        } else {

        }

//        $this->getConnection()->createQueryBuilder()
//            ->update('scheduled_job')
//            ->set('type', 'job')
//            ->where('deleted=:false')
//            ->setParameter('false', false, ParameterType::BOOLEAN)
//            ->executeQuery();

//        $this->getConnection()->createQueryBuilder()
//            ->update('scheduled_job')
//            ->set('is_active', ':true')
//            ->where('deleted=:false')
//            ->andWhere('status=:active')
//            ->setParameter('true', true, ParameterType::BOOLEAN)
//            ->setParameter('false', false, ParameterType::BOOLEAN)
//            ->setParameter('active', 'Active')
//            ->executeQuery();

//        $this->updateComposer('atrocore/core', '^1.12.0');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
