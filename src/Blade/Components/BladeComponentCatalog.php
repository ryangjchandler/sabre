<?php

declare(strict_types=1);

namespace RyanChandler\Sabre\Blade\Components;

use Forte\Ast\DirectiveNode;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use RyanChandler\Sabre\Blade\ForteDocumentParser;

final class BladeComponentCatalog
{
    private Parser $phpParser;

    public function __construct(private readonly ForteDocumentParser $documentParser)
    {
        $this->phpParser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return list<string>
     */
    public function discoverForDocumentUri(string $uri): array
    {
        try {
            $path = $this->documentParser->uriToPath($uri);
        } catch (RuntimeException) {
            return [];
        }

        $projectRoot = $this->findProjectRoot($path);

        if ($projectRoot === null) {
            return [];
        }

        return $this->discoverForProjectRoot($projectRoot);
    }

    public function resolveDefinitionPath(string $uri, string $componentName): ?string
    {
        $classPath = $this->resolveClassComponentPath($uri, $componentName);

        if ($classPath !== null && is_file($classPath)) {
            return $classPath;
        }

        $templatePath = $this->resolveComponentTemplatePath($uri, $componentName);

        if ($templatePath !== null && is_file($templatePath)) {
            return $templatePath;
        }

        return null;
    }

    public function hasRequiredSlotsForComponent(string $uri, string $componentName): bool
    {
        $templatePath = $this->resolveComponentTemplatePath($uri, $componentName);

        if ($templatePath === null || !is_file($templatePath)) {
            return false;
        }

        $contents = file_get_contents($templatePath);

        if ($contents === false) {
            return false;
        }

        $optionalOnlyDefaultSlot = preg_match('/\$slot\b\s*\?\?/', $contents) === 1
            || preg_match('/@(isset|empty)\s*\(\s*\$slot\s*\)/', $contents) === 1
            || preg_match('/@if\s*\(\s*isset\(\s*\$slot\s*\)\s*\)/', $contents) === 1;

        if (preg_match('/\$slot\b/', $contents) === 1 && !$optionalOnlyDefaultSlot) {
            return true;
        }

        foreach ($this->namedSlotDefinitionsForTemplate($templatePath) as $slot) {
            if ($slot['required']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{name: string, required: bool}>
     */
    public function slotDefinitionsForComponent(string $uri, string $componentName): array
    {
        $templatePath = $this->resolveComponentTemplatePath($uri, $componentName);

        if ($templatePath === null || !is_file($templatePath)) {
            return [];
        }

        return $this->namedSlotDefinitionsForTemplate($templatePath);
    }

    /**
     * @return list<array{name: string, isBoolean: bool}>
     */
    private function propsForTemplate(string $templatePath): array
    {
        try {
            $document = $this->documentParser->parseFile($templatePath);
        } catch (RuntimeException) {
            return [];
        }

        $props = [];

        foreach ($document->findDirectivesByName('props') as $directive) {
            if (!$directive instanceof DirectiveNode || !$directive->hasArguments()) {
                continue;
            }

            $props = array_merge($props, $this->extractPropsFromDirectiveArguments($directive->arguments()));
        }

        return $props;
    }

    /**
     * @return list<array{name: string, required: bool}>
     */
    private function namedSlotDefinitionsForTemplate(string $templatePath): array
    {
        $contents = file_get_contents($templatePath);

        if ($contents === false) {
            return [];
        }

        $props = $this->propsForTemplate($templatePath);
        $knownPropNames = array_map(
            static fn (array $definition): string => $definition['name'],
            $props
        );

        preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $contents, $matches);

        if (!isset($matches[1]) || !is_array($matches[1])) {
            return [];
        }

        $reserved = ['attributes', 'slot', 'component'];
        $definitions = [];

        foreach (array_unique($matches[1]) as $variable) {
            if (in_array($variable, $reserved, true) || in_array($variable, $knownPropNames, true)) {
                continue;
            }

            $definitions[] = [
                'name' => $this->camelToKebab($variable),
                'required' => $this->hasRequiredVariableUsage($contents, $variable),
            ];
        }

        usort($definitions, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return $definitions;
    }

    private function hasRequiredVariableUsage(string $contents, string $variable): bool
    {
        $escaped = preg_quote($variable, '/');

        if (preg_match('/\$'.$escaped.'\b(?!\s*\?\?)/', $contents) !== 1) {
            return false;
        }

        $isGuarded = preg_match('/@(isset|empty)\s*\(\s*\$'.$escaped.'\s*\)/', $contents) === 1
            || preg_match('/@if\s*\(\s*isset\(\s*\$'.$escaped.'\s*\)\s*\)/', $contents) === 1;

        return !$isGuarded;
    }

    /**
     * @return list<string>
     */
    public function discoverForProjectRoot(string $projectRoot): array
    {
        $components = array_merge(
            $this->discoverAnonymousComponents($projectRoot),
            $this->discoverClassComponents($projectRoot)
        );

        sort($components);

        return array_values(array_unique($components));
    }

    /**
     * @return list<string>
     */
    public function discoverAnonymousComponents(string $projectRoot): array
    {
        $componentPath = rtrim($projectRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'resources'
            .DIRECTORY_SEPARATOR.'views'
            .DIRECTORY_SEPARATOR.'components';

        if (!is_dir($componentPath)) {
            return [];
        }

        $components = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($componentPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (!str_ends_with($path, '.blade.php')) {
                continue;
            }

            $relative = substr($path, strlen($componentPath) + 1);
            if ($relative === false || $relative === '') {
                continue;
            }

            $name = substr($relative, 0, -strlen('.blade.php'));
            if ($name === false || $name === '') {
                continue;
            }

            $components[] = str_replace(DIRECTORY_SEPARATOR, '.', $name);
        }

        return array_values(array_unique($components));
    }

    /**
     * @return list<string>
     */
    public function discoverClassComponents(string $projectRoot): array
    {
        $componentPath = rtrim($projectRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'View'
            .DIRECTORY_SEPARATOR.'Components';

        if (!is_dir($componentPath)) {
            return [];
        }

        $components = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($componentPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (!str_ends_with($path, '.php')) {
                continue;
            }

            $relative = substr($path, strlen($componentPath) + 1);
            if ($relative === false || $relative === '') {
                continue;
            }

            $name = substr($relative, 0, -strlen('.php'));
            if ($name === false || $name === '') {
                continue;
            }

            $segments = explode(DIRECTORY_SEPARATOR, $name);
            $segments = array_map($this->segmentToKebab(...), $segments);
            $components[] = implode('.', $segments);
        }

        return array_values(array_unique($components));
    }

    /**
     * @return list<string>
     */
    public function attributesForComponent(string $uri, string $componentName): array
    {
        return array_map(
            static fn (array $definition): string => $definition['name'],
            $this->attributeDefinitionsForComponent($uri, $componentName)
        );
    }

    /**
     * @return list<array{name: string, isBoolean: bool}>
     */
    public function attributeDefinitionsForComponent(string $uri, string $componentName): array
    {
        $attributes = [];

        $componentTemplate = $this->resolveComponentTemplatePath($uri, $componentName);
        if ($componentTemplate !== null && is_file($componentTemplate)) {
            try {
                $document = $this->documentParser->parseFile($componentTemplate);

                foreach ($document->findDirectivesByName('props') as $directive) {
                    if (!$directive instanceof DirectiveNode || !$directive->hasArguments()) {
                        continue;
                    }

                    $attributes = array_merge($attributes, $this->extractPropsFromDirectiveArguments($directive->arguments()));
                }
            } catch (RuntimeException) {
            }
        }

        $classComponentPath = $this->resolveClassComponentPath($uri, $componentName);
        if ($classComponentPath !== null && is_file($classComponentPath)) {
            $attributes = array_merge($attributes, $this->extractPropsFromClassComponent($classComponentPath));
        }

        $index = [];

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];

            if (!isset($index[$name])) {
                $index[$name] = $attribute['isBoolean'];
                continue;
            }

            $index[$name] = $index[$name] || $attribute['isBoolean'];
        }

        $definitions = [];

        foreach ($index as $name => $isBoolean) {
            $definitions[] = [
                'name' => $name,
                'isBoolean' => $isBoolean,
            ];
        }

        usort($definitions, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return $definitions;
    }

    /**
     * @return list<array{name: string, isBoolean: bool}>
     */
    private function extractPropsFromDirectiveArguments(?string $arguments): array
    {
        if ($arguments === null) {
            return [];
        }

        $expression = trim($arguments);

        if (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
            $expression = substr($expression, 1, -1);
        }

        if ($expression === '') {
            return [];
        }

        try {
            $statements = $this->phpParser->parse('<?php return '.$expression.';');
        } catch (Error) {
            return [];
        }

        if ($statements === null || !isset($statements[0]) || !property_exists($statements[0], 'expr')) {
            return [];
        }

        $expr = $statements[0]->expr;

        if (!$expr instanceof Array_) {
            return [];
        }

        $props = [];

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key instanceof String_) {
                $props[] = [
                    'name' => $item->key->value,
                    'isBoolean' => $this->isBooleanExpression($item->value),
                ];
                continue;
            }

            if ($item->key === null && $item->value instanceof String_) {
                $props[] = [
                    'name' => $item->value->value,
                    'isBoolean' => false,
                ];
            }
        }

        return $props;
    }

    /**
     * @return list<array{name: string, isBoolean: bool}>
     */
    private function extractPropsFromClassComponent(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        try {
            $statements = $this->phpParser->parse($contents);
        } catch (Error) {
            return [];
        }

        if ($statements === null) {
            return [];
        }

        $classNode = $this->firstClassNode($statements);

        if ($classNode === null) {
            return [];
        }

        $constructor = $classNode->getMethod('__construct');

        if (!$constructor instanceof ClassMethod) {
            return [];
        }

        $attributes = [];

        foreach ($constructor->params as $param) {
            if (!$param instanceof Param || !$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
                continue;
            }

            $name = $param->var->name;
            if ($name === 'attributes' || $name === 'slot') {
                continue;
            }

            $attributes[] = [
                'name' => $this->camelToKebab($name),
                'isBoolean' => $this->isBooleanParameter($param),
            ];
        }

        return $attributes;
    }

    private function isBooleanExpression(Node\Expr $expression): bool
    {
        if (!$expression instanceof ConstFetch) {
            return false;
        }

        $name = strtolower($expression->name->toString());

        return $name === 'true' || $name === 'false';
    }

    private function isBooleanParameter(Param $param): bool
    {
        if ($param->type instanceof Identifier && strtolower($param->type->name) === 'bool') {
            return true;
        }

        if ($param->type instanceof NullableType && $param->type->type instanceof Identifier) {
            if (strtolower($param->type->type->name) === 'bool') {
                return true;
            }
        }

        if ($param->default instanceof ConstFetch) {
            $name = strtolower($param->default->name->toString());

            return $name === 'true' || $name === 'false';
        }

        return false;
    }

    private function firstClassNode(array $nodes): ?Class_
    {
        foreach ($nodes as $node) {
            if ($node instanceof Class_) {
                return $node;
            }

            if ($node instanceof Node\Stmt\Namespace_) {
                $classInNamespace = $this->firstClassNode($node->stmts);
                if ($classInNamespace instanceof Class_) {
                    return $classInNamespace;
                }
            }
        }

        return null;
    }

    private function findProjectRoot(string $path): ?string
    {
        $directory = is_dir($path) ? $path : dirname($path);

        while ($directory !== '' && $directory !== DIRECTORY_SEPARATOR) {
            $anonymousMarker = $directory.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'components';
            $classMarker = $directory.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'View'.DIRECTORY_SEPARATOR.'Components';

            if (is_dir($anonymousMarker) || is_dir($classMarker)) {
                return $directory;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        return null;
    }

    private function resolveComponentTemplatePath(string $uri, string $componentName): ?string
    {
        try {
            $path = $this->documentParser->uriToPath($uri);
        } catch (RuntimeException) {
            return null;
        }

        $projectRoot = $this->findProjectRoot($path);

        if ($projectRoot === null) {
            return null;
        }

        $relativeComponentPath = str_replace('.', DIRECTORY_SEPARATOR, $componentName);

        return rtrim($projectRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'resources'
            .DIRECTORY_SEPARATOR.'views'
            .DIRECTORY_SEPARATOR.'components'
            .DIRECTORY_SEPARATOR.$relativeComponentPath
            .'.blade.php';
    }

    private function resolveClassComponentPath(string $uri, string $componentName): ?string
    {
        try {
            $path = $this->documentParser->uriToPath($uri);
        } catch (RuntimeException) {
            return null;
        }

        $projectRoot = $this->findProjectRoot($path);

        if ($projectRoot === null) {
            return null;
        }

        $segments = explode('.', $componentName);
        $segments = array_map($this->segmentToStudly(...), $segments);
        $relativeClassPath = implode(DIRECTORY_SEPARATOR, $segments);

        return rtrim($projectRoot, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'app'
            .DIRECTORY_SEPARATOR.'View'
            .DIRECTORY_SEPARATOR.'Components'
            .DIRECTORY_SEPARATOR.$relativeClassPath
            .'.php';
    }

    private function segmentToStudly(string $segment): string
    {
        $parts = preg_split('/[-_]/', $segment);

        if ($parts === false) {
            return $segment;
        }

        return implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));
    }

    private function segmentToKebab(string $segment): string
    {
        $withHyphens = preg_replace('/(?<!^)[A-Z]/', '-$0', $segment);

        return strtolower($withHyphens ?? $segment);
    }

    private function camelToKebab(string $name): string
    {
        $withHyphens = preg_replace('/(?<!^)[A-Z]/', '-$0', $name);

        return strtolower($withHyphens ?? $name);
    }
}
