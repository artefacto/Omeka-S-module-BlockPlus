<?php
namespace BlockPlus;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'browsePreview' => Site\BlockLayout\BrowsePreview::class,
            'hero' => Site\BlockLayout\Hero::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\BrowsePreviewForm::class => Form\BrowsePreviewForm::class,
            Form\HeroForm::class => Form\HeroForm::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'blockplus' => [
        'block_settings' => [
            'browsePreview' => [
                'resource_type' => 'items',
                'query' => '',
                'limit' => 12,
                'heading' => '',
                'link-text' => 'Browse all', // @translate
                'partial' => '',
            ],
            'hero' => [
                'asset' => null,
                'text' => '',
                'button' => 'Discover documents…', // @translate
                'url' => 'item',
            ],
        ],
    ],
];
