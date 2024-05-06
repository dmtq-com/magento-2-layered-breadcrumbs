<?php
declare(strict_types=1);

namespace DMTQ\LayeredBreadcrumbs\ViewModel\Product;

use Magento\Catalog\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

/**
 * Product breadcrumbs view model.
 */
class Breadcrumbs extends DataObject implements ArgumentInterface
{
    const BREADCRUMBS_ENABLED_PATH = 'layered_breadcrumbs/general/enabled';
    const BREADCRUMBS_CATEGORIES_PATH = 'layered_breadcrumbs/general/categories';
    const SHOW_ALL_PARENT_PATH = 'layered_breadcrumbs/general/show_all_parent';

    /**
     * @var Data
     */
    private Data $catalogData;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var Escaper
     */
    private Escaper $escaper;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var CategoryCollectionFactory
     */
    public CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @param Data $catalogData
     * @param ScopeConfigInterface $scopeConfig
     * @param Escaper $escaper
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        Data                      $catalogData,
        ScopeConfigInterface      $scopeConfig,
        Escaper                   $escaper,
        StoreManagerInterface     $storeManager,
        CategoryCollectionFactory $categoryCollectionFactory
    )
    {
        parent::__construct();
        $this->catalogData = $catalogData;
        $this->scopeConfig = $scopeConfig;
        $this->escaper = $escaper;
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getBreadcrumbs(): array
    {
        $data = [];
        $data['home'] = [
            'label' => __('Home'),
            'title' => __('Go to Home Page'),
            'link' => $this->storeManager->getStore()->getBaseUrl(),
            'first' => true
        ];
        if (!$this->catalogData->getProduct()) {
            return $data;
        }
        $product = $this->catalogData->getProduct();
        $enabled = $this->scopeConfig->getValue(self::BREADCRUMBS_ENABLED_PATH);
        $categoryIds = $this->scopeConfig->getValue(self::BREADCRUMBS_CATEGORIES_PATH);
        $mainCategoryIds = explode(',', $categoryIds ?? '');
        if ($enabled && $mainCategoryIds) {
            $productCategoryIds = $product->getCategoryIds();
            $mainCategoryIds = array_map('intval', $mainCategoryIds);
            if (!empty($productCategoryIds)) {
                $collection = $this->categoryCollectionFactory->create();
                $collection->addAttributeToFilter('entity_id', ['in' => $productCategoryIds])
                    ->addAttributeToFilter('is_active', 1)
                    ->addAttributeToSelect(['name', 'is_active', 'parent_id', 'url_key'])
                    ->setStoreId($this->storeManager->getStore()->getId());
                $categoryById = [];
                foreach ($collection as $category) {
                    foreach ([$category->getId(), $category->getParentId()] as $categoryId) {
                        if (!isset($categoryById[$categoryId])) {
                            $categoryById[$categoryId] = ['id' => $categoryId];
                        }
                    }
                    if ($category->getIsActive()) {
                        $categoryData = [
                            'is_active' => $category->getIsActive(),
                            'name' => $category->getName(),
                            'url' => $category->getUrl()
                        ];
                        $categoryById[$category->getId()] = array_merge($categoryById[$category->getId()], $categoryData);
                        $categoryById[$category->getParentId()]['child'][] = &$categoryById[$category->getId()];
                    }
                }

                $intersects = array_intersect($mainCategoryIds, array_keys($categoryById));
                if (!empty($intersects)) {
                    $mainCatID = (int)reset($intersects);
                    $this->buildCategoryBreadcrumbs($mainCatID, $categoryById, $data);
                }
            }
        }
        $data['product'] = [
            'label' => $this->escaper->escapeHtml($product->getName()),
            'title' => $this->escaper->escapeHtml($product->getName()),
            'link' => '',
            'last' => true
        ];
        return $data;
    }

    /**
     * Build category breadcrumbs
     * @param int $mainCatID
     * @param array $categoryById
     * @param array $data
     */
    private function buildCategoryBreadcrumbs(int $mainCatID, array $categoryById, array &$data): void
    {
        $this->addBreadcrumbRecursively($mainCatID, $categoryById, $data);
    }

    private function addBreadcrumbRecursively(int $categoryId, array $categoryById, array &$data): void
    {
        if (!empty($categoryById[$categoryId])) {
            $category = $categoryById[$categoryId];
            $this->addBreadcrumb($data, $category);

            if (!empty($category['child'])) {
                $this->addBreadcrumbRecursively((int)$category['child'][0]['id'], $categoryById, $data);
            }
        }
    }

    /**
     * Add breadcrumb
     * @param array $data
     * @param array $category
     * @throws NoSuchEntityException
     */
    private function addBreadcrumb(array &$data, array $category): void
    {
        if (!empty($category['name'])) {
            $data['category' . $category['id']] = [
                'label' => $this->escaper->escapeHtml($category['name']),
                'title' => $this->escaper->escapeHtml($category['name']),
                'link' => $category['url']
            ];
        } else {
            $showAllParent = $this->scopeConfig->getValue(self::SHOW_ALL_PARENT_PATH);
            if ($showAllParent) {
                $collection = $this->categoryCollectionFactory->create();
                $categoryItem = $collection->addAttributeToFilter('entity_id', $category['id'])
                    ->addAttributeToFilter('is_active', 1)
                    ->addAttributeToSelect(['name', 'is_active', 'parent_id', 'url_key'])
                    ->setStoreId($this->storeManager->getStore()->getId())
                    ->getFirstItem();
                if ($categoryItem->getId()) {
                    $data['category' . $categoryItem->getId()] = [
                        'label' => $this->escaper->escapeHtml($categoryItem->getName()),
                        'title' => $this->escaper->escapeHtml($categoryItem->getName()),
                        'link' => $categoryItem->getUrl()
                    ];
                }
            }
        }

    }
}
