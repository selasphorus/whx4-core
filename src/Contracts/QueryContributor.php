<?php
namespace atc\WXC\Contracts;

interface QueryContributor
{
    /** @param array<string,mixed> $args @param array<string,mixed> $params */
    public function adjustQueryArgs(array $args, array $params): array;
}
