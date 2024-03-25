<div class="detail" id="{{id}}">
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
        </div>
        <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>
        <div class="clearfix"></div>
    </div>

    <div class="row">
        <div class="overview list col-md-12">
            <table class="table full-table table-striped table-fixed table-bordered-inside">
                <thead>
                   <tr>
                       <th></th>
                       <th>
                           {{translate 'currentModel' scope='Connector' category='labels'}}
                       </th>
                       <th>
                           {{translate 'otherFrom' scope='Connector' category='labels'}} {{distantModel._connection}}
                       </th>
                       <th width="25"></th>
                   </tr>

                </thead>
                <tbody>
                    {{#each fieldsArr}}
                       {{#if isField }}
                        <tr class="list-row {{#if  different}} danger {{/if}}">
                            <td class="cell">{{translate label scope=../scope category='fields'}}</td>
                            <td class="cell ">
                                <div class="field">{{{var current ../../this}}}</div>
                            </td>
                            <td class="cell">
                                <div class="field">{{{var other ../../this}}}</div>
                            </td>
                            {{#if isLink }}
                                <td class="cell" data-name="buttons">
                                    <div class="list-row-buttons btn-group pull-right">
                                        <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                                            <span class="fas fa-ellipsis-v"></span>
                                        </button>
                                        <ul class="dropdown-menu pull-right">
                                            {{#if isLinkMultiple }}
                                            <li> <a class="disabled panel-title">  {{translate 'QuickCompare' scope='Connector'}}</a></li>
                                                {{#each values }}
                                                    <li>
                                                        <a href="#" class="action" data-action="quickCompare"
                                                           data-scope="{{../foreignScope}}"
                                                           data-id="{{id}}">
                                                           {{ name }}
                                                        </a>
                                                    </li>
                                                {{/each}}
                                            {{else}}
                                            <li><a href="#" class="action" data-action="quickCompare" data-scope="{{foreignScope}}" data-id="{{foreignId}}">QuickCompare</a></li>
                                            {{/if}}
                                        </ul>
                                    </div>
                                </td>
                            {{else}}
                             <td></td>
                            {{/if}}
                        </tr>
                       {{else}}
                           {{#if separator }}
                              <tr>
                                  <td></td>
                                  <td></td>
                                  <td></td>
                              </tr>
                              <tr>
                                  <th>
                                      {{translate 'attribute' scope='Connector' category='labels'}} ({{translate 'channel' scope='Connector' category='labels'}}, {{translate 'language' scope='Connector' category='labels'}})
                                  </th>
                                  <th>{{translate 'currentModel' scope='Connector' category='labels'}}</th>
                                  <th> {{translate 'otherFrom' scope='Connector' category='labels'}} {{distantModel._connection}}</th>
                              </tr>
                            {{else}}
                                <tr class="list-row  {{#if  different}} danger {{/if}}" data-id="{{attributeId}}">
                                    <td class="cell"><a href="#Attribute/view/{{attributeId}}"> {{attributeName}} ({{attributeChannel}}, {{language}})</a></td>
                                    <td class="cell current">
                                     {{{var current ../../../this}}}
                                    </td>
                                    <td class="cell other">
                                        {{{var other ../../../this}}}
                                    </td>
                                    <td class="cell" data-name="buttons">
                                        <div class="list-row-buttons btn-group pull-right">
                                            <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                                                <span class="fas fa-ellipsis-v"></span>
                                            </button>
                                            <ul class="dropdown-menu pull-right">
                                                <li> <a class="disabled panel-title">  {{translate 'QuickCompare' scope='Connector' category='labels'}}</a></li>
                                                <li>
                                                    <a href="#" class="action" data-action="quickCompare"
                                                       data-scope="Attribute"
                                                       data-id="{{attributeId}}">
                                                        {{translate 'attribute' scope='Connector' category='labels'}}
                                                    </a>
                                                </li>
                                                {{#if canQuickCompare }}
                                                    <li>
                                                        <a href="#" class="action" data-action="quickCompare"
                                                           data-scope="ProductAttributeValue"
                                                           data-id="{{productAttributeId}}">
                                                            {{translate 'Value' scope='Attribute' category='labels'}}
                                                        </a>
                                                    </li>
                                                {{/if}}
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            {{/if}}
                       {{/if}}
                    {{/each}}
                </tbody>
            </table>
        </div>
    </div>
</div>