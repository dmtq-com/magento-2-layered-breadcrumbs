<?php
namespace DMTQ\LayeredBreadcrumbs\Block\Adminhtml\System;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class CategoryState
 */
class CategoryState extends AbstractCategory
{
    const CONFIG_PATH = 'layered_breadcrumbs/general/categories';

    /**
     * @inheritdoc
     */
    public function _getElementHtml(AbstractElement $element): string
    {
        $html = '<div class="admin__field-control">';

        $html .= '<div id="layered_breadcrumbs_state_categories"  class="admin__field" data-bind="scope:\'state_categories\'" data-index="index">';
        $html .= '<!-- ko foreach: elems() -->';
        $html .= '<input class="input-text admin__control-text" type="text" name="groups[general][fields][categories][value]" data-bind="value: value" style="display: none;"/>';
        $html .= '<!-- ko template: elementTmpl --><!-- /ko -->';
        $html .= '<!-- /ko -->';
        $html .= '</div>';

        $html .= $this->getScriptHtml();

        return $html;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getScriptHtml()
    {
        return '<script type="text/x-magento-init">
            {
                "#layered_breadcrumbs_state_categories": {
                    "Magento_Ui/js/core/app": {
                        "components": {
                            "state_categories": {
                                "component": "uiComponent",
                                "children": {
                                    "select_category": {
                                        "component": "Magento_Catalog/js/components/new-category",
                                        "config": {
                                            "filterOptions": true,
                                            "disableLabel": true,
                                            "chipsEnabled": true,
                                            "levelsVisibility": "1",
                                            "elementTmpl": "ui/grid/filters/elements/ui-select",
                                             "options": ' . json_encode($this->getOptions()) . ',
                                            "value": ' . json_encode($this->getValues(self::CONFIG_PATH)) . ',
                                            "listens": {
                                                "index=create_category:responseData": "setParsed",
                                                "newOption": "toggleOptionSelected"
                                            },
                                            "config": {
                                                "dataScope": "select_category",
                                                "sortOrder": 10
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        </script>';
    }
}
