/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/connection/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [
                {
                    "action": "testConnection",
                    "label": this.translate('testConnection', 'labels', 'Connection')
                }
            ];
        },

        actionTestConnection() {
            this.notify('Loading...');
            this.ajaxPostRequest('Connection/action/testConnection', {id: this.model.get('id')}).then(() => {
                this.notify(this.translate('connectionSuccess', 'labels', 'Connection'), 'success');
            });
        },

    });
});
