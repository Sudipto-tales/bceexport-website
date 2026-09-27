<?php

/**
 * Resource registry for BCE Export Admin Panel.
 *
 * Defines table schema, public keys, fields, data types, search/sort capabilities,
 * filters, and relationships for the generic REST API and admin controllers.
 */

return [

    'products' => [
        'table' => 'products',
        'key' => 'slug',
        'label' => 'name',
        'search' => ['name', 'short_description', 'description'],
        'sort' => ['name', 'order', 'status', 'updatedAt'],
        'fields' => [
            'name' => 'string',
            'categoryId' => ['type' => 'ref', 'column' => 'category_id', 'target' => 'categories'],
            'shortDescription' => 'text',
            'description' => 'text',
            'image' => 'string',
            'gallery' => 'json',
            'features' => 'json',
            'specifications' => 'json',
            'featured' => 'bool',
        ],
        'filters' => [
            'categoryId' => ['type' => 'ref', 'column' => 'category_id', 'target' => 'categories'],
            'featured' => ['type' => 'bool'],
        ],
        'required' => ['name', 'categoryId'],
    ],

    'categories' => [
        'table' => 'categories',
        'key' => 'slug',
        'label' => 'name',
        'search' => ['name', 'description'],
        'sort' => ['name', 'order', 'status', 'updatedAt'],
        'fields' => [
            'name' => 'string',
            'description' => 'text',
            'image' => 'string',
            'icon' => 'string',
        ],
        'required' => ['name'],
        'dependents' => [
            ['table' => 'products', 'column' => 'category_id', 'label' => 'product', 'resource' => 'products'],
        ],
    ],

    'team-members' => [
        'table' => 'team_members',
        'key' => 'slug',
        'label' => 'name',
        'search' => ['name', 'role', 'bio'],
        'sort' => ['name', 'role', 'order', 'status', 'updatedAt'],
        'fields' => [
            'name' => 'string',
            'role' => 'string',
            'photo' => 'string',
            'bio' => 'text',
            'email' => 'string',
            'phone' => 'string',
            'socialLinks' => 'json',
        ],
        'required' => ['name', 'role'],
    ],

    'certificates' => [
        'table' => 'certificates',
        'key' => 'slug',
        'label' => 'title',
        'search' => ['title', 'issuer'],
        'sort' => ['title', 'order', 'status', 'updatedAt'],
        'fields' => [
            'title' => 'string',
            'issuer' => 'string',
            'image' => 'string',
            'issueDate' => 'string',
            'expiryDate' => 'string',
        ],
        'required' => ['title'],
    ],

    'testimonials' => [
        'table' => 'testimonials',
        'key' => 'public_id',
        'label' => 'name',
        'search' => ['name', 'text', 'role', 'company'],
        'sort' => ['name', 'rating', 'order', 'status', 'updatedAt'],
        'fields' => [
            'text' => 'text',
            'name' => 'string',
            'role' => 'string',
            'company' => 'string',
            'photo' => 'string',
            'rating' => 'int',
            'featured' => 'bool',
        ],
        'filters' => ['featured' => ['type' => 'bool']],
        'required' => ['text', 'name'],
    ],

    'enquiries' => [
        'defaultSort' => 'receivedAt',
        'defaultDir' => 'desc',
        'table' => 'enquiries',
        'key' => 'public_id',
        'label' => 'name',
        'search' => ['name', 'email', 'phone', 'subject', 'message'],
        'sort' => ['name', 'receivedAt', 'status', 'priority', 'order'],
        'create' => false,
        'fields' => [
            'name' => ['type' => 'string', 'readonly' => true],
            'email' => ['type' => 'string', 'readonly' => true],
            'phone' => ['type' => 'string', 'readonly' => true],
            'subject' => ['type' => 'string', 'readonly' => true],
            'message' => ['type' => 'text', 'readonly' => true],
            'product' => ['type' => 'string', 'readonly' => true],
            'source' => ['type' => 'string', 'readonly' => true],
            'receivedAt' => ['type' => 'datetime', 'readonly' => true],
            'assignedTo' => ['type' => 'ref', 'column' => 'assigned_to', 'target' => 'users'],
            'priority' => ['type' => 'string', 'default' => 'normal'],
            'replies' => 'json',
            'internalNotes' => 'json',
        ],
        'filters' => [
            'source' => ['type' => 'string'],
            'priority' => ['type' => 'string'],
        ],
        'statusValues' => ['new', 'replied', 'closed', 'spam'],
    ],

    'users' => [
        'table' => 'users',
        'key' => 'public_id',
        'label' => 'name',
        'search' => ['name', 'email', 'phone'],
        'sort' => ['name', 'email', 'order', 'status', 'updatedAt'],
        'fields' => [
            'name' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'avatar' => 'string',
            'landingPage' => 'string',
            'password' => ['type' => 'password', 'writeonly' => true],
        ],
        'required' => ['name', 'email'],
        'unique' => ['email'],
        'statusValues' => ['active', 'suspended'],
    ],

    'blog-categories' => [
        'table' => 'blog_categories',
        'key' => 'slug',
        'label' => 'name',
        'search' => ['name', 'description'],
        'sort' => ['name', 'order', 'status', 'updatedAt'],
        'fields' => [
            'name' => 'string',
            'description' => 'text',
        ],
        'required' => ['name'],
        'dependents' => [
            ['table' => 'blog_posts', 'column' => 'category_id', 'label' => 'blog post', 'resource' => 'blog-posts'],
        ],
    ],

    'blog-posts' => [
        'table' => 'blog_posts',
        'key' => 'slug',
        'label' => 'title',
        'search' => ['title', 'excerpt', 'body', 'author_name'],
        'sort' => ['title', 'publishedAt', 'order', 'status', 'updatedAt'],
        'fields' => [
            'title' => 'string',
            'excerpt' => 'text',
            'body' => 'text',
            'coverImage' => 'string',
            'categoryId' => ['type' => 'ref', 'column' => 'category_id', 'target' => 'blog-categories'],
            'authorName' => 'string',
            'tags' => 'json',
            'featured' => 'bool',
            'publishedAt' => 'datetime',
        ],
        'filters' => [
            'categoryId' => ['type' => 'ref', 'column' => 'category_id', 'target' => 'blog-categories'],
            'featured' => ['type' => 'bool'],
        ],
        'required' => ['title'],
    ],
];
