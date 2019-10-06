<?php
namespace BlockPlus\Site\BlockLayout;

use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Zend\View\Renderer\PhpRenderer;

class SearchResults extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Search form and results'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $query = [];
        parse_str(ltrim($data['query'], '? '), $query);
        $data['query'] = $query;
        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['blockplus']['block_settings']['searchResults'];
        $blockFieldset = \BlockPlus\Form\SearchResultsFieldset::class;

        $data = $block ? $block->data() + $defaultSettings : $defaultSettings;

        $data['query'] = http_build_query($data['query']);

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        return $view->formCollection($fieldset);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        // Similar to BrowsePreview::render(), but with a different query.

        $resourceType = $block->dataValue('resource_type', 'items');

        $defaultQuery = $block->dataValue('query', []) + ['search' => ''];
        $query = $view->params()->fromQuery() + $defaultQuery;

        $site = $block->page()->site();
        if ($view->siteSetting('browse_attached_items', false)) {
            $query['site_attachments_only'] = true;
        }

        $query['site_id'] = $site->id();

        $limit = $block->dataValue('limit', 12);
        $pagination = $limit && $block->dataValue('pagination');
        if ($pagination) {
            $currentPage = $view->params()->fromQuery('page', 1);
            $query['page'] = $currentPage;
            $query['per_page'] = $limit;
        } elseif ($limit) {
            $query['limit'] = $limit;
        }

        $sortBy = $view->params()->fromQuery('sort_by');
        if ($sortBy) {
            $query['sort_by'] = $sortBy;
        } elseif (!isset($query['sort_by'])) {
            $query['sort_by'] = 'created';
        }

        $sortOrder = $view->params()->fromQuery('sort_order');
        if ($sortOrder) {
            $query['sort_order'] = $sortOrder;
        } elseif (!isset($query['sort_order'])) {
            $query['sort_order'] = 'desc';
        }

        /** @var \Omeka\Api\Response $response */
        $api = $view->api();
        $response = $api->search($resourceType, $query);

        // TODO Currently, there can be only one pagination by page.
        if ($pagination) {
            $totalCount = $response->getTotalResults();
            $pagination = [
                'total_count' => $totalCount,
                'current_page' => $currentPage,
                'limit' => $limit,
            ];
            $view->pagination(null, $totalCount, $currentPage, $limit);
        }

        /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $resourceTemplate */
        $resourceTemplate = $block->dataValue('resource_template');
        if ($resourceTemplate) {
            try {
                $resourceTemplate = $api->read('resource_templates', $resourceTemplate)->getContent();
            } catch (\Exception $e) {
            }
        }

        $sortHeadings = $block->dataValue('sort_headings', []);
        if ($sortHeadings) {
            $translate = $view->plugin('translate');
            foreach ($sortHeadings as $key => $sortHeading) {
                switch ($sortHeading) {
                    case 'created':
                        $label = $translate('Created'); // @translate
                        break;
                    case 'resource_class_label':
                        $label = $translate('Class'); // @translate
                        break;
                    default:
                        $property = $api->searchOne('properties', ['term' => $sortHeading])->getContent();
                        if ($property) {
                            if ($resourceTemplate) {
                                $templateProperty = $resourceTemplate->resourceTemplateProperty($property->id());
                                if ($templateProperty) {
                                    $label = $translate($templateProperty->alternateLabel() ?: $property->label());
                                    break;
                                }
                            }
                            $label = $translate($property->label());
                        } else {
                            unset($sortHeadings[$key]);
                            continue;
                        }
                        break;
                }
                $sortHeadings[$key] = [
                    'label' => $label,
                    'value' => $sortHeading,
                ];
            }
            $sortHeadings = array_filter($sortHeadings);
        }

        $resources = $response->getContent();

        $resourceTypes = [
            'items' => 'item',
            'item_sets' => 'item-set',
            'media' => 'media',
        ];

        $template = $block->dataValue('template') ?: 'common/block-layout/search-results';

        return $view->partial($template, [
            'heading' => $block->dataValue('heading'),
            'resourceType' => $resourceTypes[$resourceType],
            'resources' => $resources,
            'query' => $query,
            'pagination' => $pagination,
            'sortHeadings' => $sortHeadings,
        ]);
    }
}