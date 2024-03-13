/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/modals/update-details', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:composer/modals/update-details',

        setup() {
            Dep.prototype.setup.call(this);

            this.setupHeader();
            this.setupButtonList();
        },

        setupHeader() {
            this.header = this.translate('Details');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

        data() {
            return {
                output: this.options.output
            };
        },

    })
);