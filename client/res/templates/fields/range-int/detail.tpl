{{#if isNull}}<span class="text-gray">{{{translate 'Null'}}}</span>{{else}}{{{value}}}{{/if}} {{#if unitFieldName}}{{#if unitValue}}{{unitValueTranslate}}{{else}}<span class="text-gray">{{{translate 'Null'}}}</span>{{/if}}{{/if}}