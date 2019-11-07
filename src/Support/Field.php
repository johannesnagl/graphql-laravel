<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Support;

use Closure;
use Validator;
use Illuminate\Support\Arr;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\WrappingType;
use Rebing\GraphQL\Error\ValidationError;
use GraphQL\Type\Definition\InputObjectType;
use Rebing\GraphQL\Error\AuthorizationError;
use GraphQL\Type\Definition\Type as GraphqlType;

abstract class Field
{
    protected $attributes = [];

    /**
     * Override this in your queries or mutations
     * to provide custom authorization.
     *
     * @param  mixed  $root
     * @param  array  $args
     * @param  mixed  $ctx
     * @param  ResolveInfo|null  $resolveInfo
     * @param  Closure|null  $getSelectFields
     * @return bool
     */
    public function authorize($root, array $args, $ctx, ResolveInfo $resolveInfo = null, Closure $getSelectFields = null): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [];
    }

    abstract public function type(): GraphqlType;

    /**
     * @return array<string,array>
     */
    public function args(): array
    {
        return [];
    }

    /**
     * Define custom Laravel Validator messages as per Laravel 'custom error messages'.
     *
     * @param array $args submitted arguments
     *
     * @return array
     */
    public function validationErrorMessages(array $args = []): array
    {
        return [];
    }

    protected function rules(array $args = []): array
    {
        return [];
    }

    public function getRules(): array
    {
        $arguments = func_get_args();

        $rules = call_user_func_array([$this, 'rules'], $arguments);
        $argsRules = [];
        foreach ($this->args() as $name => $arg) {
            if (isset($arg['rules'])) {
                $argsRules[$name] = $this->resolveRules($arg['rules'], $arguments);
            }

            if (isset($arg['type'])
                && ($arg['type'] instanceof NonNull || isset(Arr::get($arguments, 0, [])[$name]))) {
                $argsRules = array_merge($argsRules, $this->inferRulesFromType($arg['type'], $name, $arguments));
            }
        }

        return array_merge($argsRules, $rules);
    }

    /**
     * @param  array|string|callable  $rules
     * @param  array  $arguments
     * @return array|string
     */
    public function resolveRules($rules, array $arguments)
    {
        if (is_callable($rules)) {
            return call_user_func_array($rules, $arguments);
        }

        return $rules;
    }

    public function inferRulesFromType(GraphqlType $type, string $prefix, array $resolutionArguments): array
    {
        $rules = [];

        // make sure we are dealing with the actual type
        if ($type instanceof NonNull) {
            $type = $type->getWrappedType();
        }

        // if it is an array type, add an array validation component
        if ($type instanceof ListOfType) {
            $prefix = "{$prefix}.*";
        }

        // make sure we are dealing with the actual type
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType(true);
        }

        // if it is an input object type - the only type we care about here...
        if ($type instanceof InputObjectType) {
            // merge in the input type's rules
            $rules = array_merge($rules, $this->getInputTypeRules($type, $prefix, $resolutionArguments));
        }

        // Ignore scalar types

        return $rules;
    }

    public function getInputTypeRules(InputObjectType $input, string $prefix, array $resolutionArguments): array
    {
        $rules = [];

        foreach ($input->getFields() as $name => $field) {
            $key = "{$prefix}.{$name}";

            // get any explicitly set rules
            if (isset($field->rules)) {
                $rules[$key] = $this->resolveRules($field->rules, $resolutionArguments);
            }

            $type = $field->type;
            if ($field->type instanceof WrappingType) {
                $type = $field->type->getWrappedType(true);
            }

            // then recursively call the parent method to see if this is an
            // input object, passing in the new prefix
            if ($type instanceof InputObjectType) {
                // in case the field is a self reference we must not do
                // a recursive call as it will never stop
                if ($type->toString() == $input->toString()) {
                    continue;
                }
            }
            $rules = array_merge($rules, $this->inferRulesFromType($field->type, $key, $resolutionArguments));
        }

        return $rules;
    }

    protected function getResolver(): ?Closure
    {
        if (! method_exists($this, 'resolve')) {
            return null;
        }

        $resolver = [$this, 'resolve'];
        $authorize = [$this, 'authorize'];

        return function () use ($resolver, $authorize) {
            // 0 - the "root" object; `null` for queries, otherwise the parent of a type
            // 1 - the provided `args` of the query or type (if applicable), empty array otherwise
            // 2 - the "GraphQL query context" (see \Rebing\GraphQL\GraphQLController::queryContext)
            // 3 - \GraphQL\Type\Definition\ResolveInfo as provided by the underlying GraphQL PHP library
            // 4 (!) - added by this library, encapsulates creating a `SelectFields` instance
            $arguments = func_get_args();

            // Validate mutation arguments
            $args = $arguments[1];
            $rules = call_user_func_array([$this, 'getRules'], [$args]);
            if (count($rules)) {

                // allow our error messages to be customised
                $messages = $this->validationErrorMessages($args);

                $validator = Validator::make($args, $rules, $messages);
                if ($validator->fails()) {
                    throw new ValidationError('validation', $validator);
                }
            }

            // Add the 'selects and relations' feature as 5th arg
            if (isset($arguments[3])) {
                $arguments[] = function (int $depth = null) use ($arguments): SelectFields {
                    $ctx = $arguments[2] ?? null;

                    return new SelectFields($arguments[3], $this->type(), $arguments[1], $depth ?? 5, $ctx);
                };
            }

            // Authorize
            if (call_user_func_array($authorize, $arguments) != true) {
                throw new AuthorizationError('Unauthorized');
            }

            return call_user_func_array($resolver, $arguments);
        };
    }

    /**
     * Get the attributes from the container.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $attributes = $this->attributes();

        $attributes = array_merge(
            $this->attributes,
            ['args' => $this->args()],
            $attributes
        );

        $attributes['type'] = $this->type();

        $resolver = $this->getResolver();
        if (isset($resolver)) {
            $attributes['resolve'] = $resolver;
        }

        return $attributes;
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getAttributes();
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();

        return $attributes[$key] ?? null;
    }

    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }
}
