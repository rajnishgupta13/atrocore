/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/ui-handler/fields/trigger-fields', 'views/fields/entity-fields', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:triggerAction', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                if (this.model.get('triggerAction') === 'ui_on_focus') {
                    this.$el.parent().show();
                } else {
                    this.$el.parent().hide();
                }
            }
        },

        getEntityFields() {
            let entity = this.getEntityType();

            let result = {};
            let notAvailableTypes = [
                'address',
                'attachmentMultiple',
                'currencyConverted',
                'linkParent',
                'personName',
                'autoincrement'
            ];
            let notAvailableFieldsList = [
                'createdAt',
                'modifiedAt'
            ];
            if (entity) {
                let fields = this.getMetadata().get(['entityDefs', entity, 'fields']) || {};
                result.id = {
                    type: 'varchar'
                };
                Object.keys(fields).forEach(name => {
                    let field = fields[name];
                    if (!notAvailableFieldsList.includes(name) && !notAvailableTypes.includes(field.type)) {
                        result[name] = field;
                    }
                });
            }

            return result;
        },

    });
});

