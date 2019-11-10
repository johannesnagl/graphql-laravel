<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Tests\Support\Queries;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Query;
use Rebing\GraphQL\Support\SelectFields;
use Rebing\GraphQL\Tests\Support\Models\Post;

class PostQueryWithSelectFieldsClassInjectionQuery extends Query
{
    protected $attributes = [
        'name' => 'postWithSelectFieldClassInjection',
    ];

    public function type(): Type
    {
        return GraphQL::type('Post');
    }

    public function args(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::id()),
            ],
        ];
    }

    public function resolve($root, $args, $ctx, SelectFields $fields, ResolveInfo $info)
    {
        return Post::select($fields->getSelect())
            ->findOrFail($args['id']);
    }
}
