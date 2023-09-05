<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.md, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Espo\Console\Cron;
use Atro\Core\Migration\Base;

class V1Dot4Dot13 extends Base
{
    public function up(): void
    {
        file_put_contents(Cron::DAEMON_KILLER, '1');
    }

    public function down(): void
    {
        file_put_contents(Cron::DAEMON_KILLER, '1');
    }
}
