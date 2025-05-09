/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/enum-default', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
            this.listenTo(this.model, 'change:options', function () {
                this.prepareOptionsList();
                this.reRender();
            }, this);
        },

        prepareOptionsList() {
            this.params.options = [''];
            this.translatedOptions = {'': ''};

            (this.model.get('options') || []).forEach((option, k) => {
                this.params.options.push(option);
                this.translatedOptions[option] = option;
            });
        },

        validate() {
            if (this.model.get('prohibitedEmptyValue') && this.model.get('default') === '') {
                this.showValidationMessage(this.translate('defaultEnumValueCannotBeEmpty', 'messages'));
                return true;
            }

            return false;
        },

    });

});
