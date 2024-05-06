<?php
namespace DMTQ\LayeredBreadcrumbs\Block\Adminhtml\System;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\BlockInterface;

/**
 * Class AbstractCategory
 */
abstract class AbstractCategory extends Field implements BlockInterface
{
    /**
     * @var array
     */
    protected $categoriesTree;

    /**
     * @var CategoryCollectionFactory
     */
    public CategoryCollectionFactory $collectionFactory;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;


    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * AbstractCategory constructor.
     *
     * @param Context $context
     * @param CategoryCollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryCollectionFactory $collectionFactory,
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->request           = $request;
        $this->scopeConfig = $scopeConfig;

        parent::__construct($context, $data);
    }

    /**
     * @return array|mixed
     * @throws LocalizedException
     */
    protected function getOptions(): mixed
    {
        return $this->getCategoriesTree();
    }

    /**
     * get Active Category
     * @return array|mixed
     * @throws LocalizedException
     */
    protected function getCategoriesTree(): mixed
    {
        if ($this->categoriesTree === null) {
            $storeId                 = $this->request->getParam('store');
            $matchingNamesCollection = $this->collectionFactory->create();

            $matchingNamesCollection->addAttributeToSelect('path')
                ->addAttributeToFilter('entity_id', ['neq' => CategoryModel::TREE_ROOT_ID])
                ->setStoreId($storeId);

            $shownCategoriesIds = [];

            /** @var CategoryModel $category */
            foreach ($matchingNamesCollection as $category) {
                foreach (explode('/', $category->getPath()) as $parentId) {
                    $shownCategoriesIds[$parentId] = 1;
                }
            }

            $collection = $this->collectionFactory->create();

            $collection->addAttributeToFilter('entity_id', ['in' => array_keys($shownCategoriesIds)])
                ->addAttributeToSelect(['name', 'is_active', 'parent_id'])
                ->setStoreId($storeId);

            $categoryById = [
                CategoryModel::TREE_ROOT_ID => [
                    'value' => CategoryModel::TREE_ROOT_ID
                ],
            ];

            foreach ($collection as $category) {
                foreach ([$category->getId(), $category->getParentId()] as $categoryId) {
                    if (!isset($categoryById[$categoryId])) {
                        $categoryById[$categoryId] = ['value' => $categoryId];
                    }
                }
                if ($category->getIsActive()) {
                    $categoryById[$category->getId()]['is_active']        = $category->getIsActive();
                    $categoryById[$category->getId()]['label']            = $category->getName();
                    $categoryById[$category->getParentId()]['optgroup'][] = &$categoryById[$category->getId()];
                }

            }

            $this->categoriesTree = $categoryById[CategoryModel::TREE_ROOT_ID]['optgroup'];
        }

        return $this->categoriesTree;
    }

    /**
     * @param $configPath
     *
     * @return array
     */
    public function getValues($configPath): array
    {
        $values = $this->getValuesConfig($configPath);
        if (empty($values)) {
            return [];
        }

        $options    = [];
        $collection = $this->collectionFactory->create()->addIdFilter($values);
        foreach ($collection as $category) {
            /** @var Collection $category */
            $options[] = $category->getId();
        }

        return $options;
    }

    /**
     * @param $configPath
     *
     * @return false|mixed|string[]
     */
    public function getValuesConfig($configPath): mixed
    {
        $categoryIds = $this->scopeConfig->getValue($configPath);
        if (!is_array($categoryIds)) {
            $categoryIds = explode(',', $categoryIds ?? '');
        }

        return $categoryIds;
    }

}
