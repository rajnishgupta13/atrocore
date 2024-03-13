/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/modals/show-message', 'views/modal',
    Dep => Dep.extend({

        className: 'dialog queue-modal',

        template: 'treo-core:queue-manager/modals/show-message',

        buttonList: [
            {
                name: 'cancel',
                label: 'Close'
            }
        ],

        setup() {
            Dep.prototype.setup.call(this);

            this.header = this.translate('message', 'labels', 'QueueItem');
        },

        data() {
            return {
                message: this.options.message
            };
        },

    })
);

