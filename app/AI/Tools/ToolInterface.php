<?php

namespace App\AI\Tools;

interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    public function parameters(): array;

    public function execute(array $arguments): mixed;
}