<?php
if (!defined('ABSPATH')) {
    exit;
}
return [

];

//return [
//    'versions' => [
//        '100' => [
//            'tables' => [
//                'items' => [
//                    'name' => 'items',
//                    'columns' => [
//                        'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
//                        'title' => 'varchar(255) NOT NULL',
//                        'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
//                        'PRIMARY KEY' => '(id)'
//                    ]
//                ]
//            ],
//
//            'default_data' => [
//                'surveys' => [
//                    [
//                        'title' => '示例九宫格调查',
//                        'description' => '这是一个默认的九宫格调查示例',
//                        'status' => 'published'
//                    ]
//                ]
//            ],
//        ],
//        '110' => [
//            'tables' => [
//                'items' => [
//                    'add_columns' => [
//                        'status' => 'varchar(20) DEFAULT "draft"'
//                    ]
//                ]
//            ]
//        ]
//    ]
//];