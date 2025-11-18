<?php
/**
 * This file is part of the gromnan/symfony-config-xml-to-php package.
 *
 * (c) Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GromNaN\SymfonyConfigXmlToPhp\Converter;

use GromNaN\SymfonyConfigXmlToPhp\Converter\Elements\ElementProcessorFactory;
use GromNaN\SymfonyConfigXmlToPhp\Converter\Elements\ArgumentProcessor;
use GromNaN\SymfonyConfigXmlToPhp\Exception\ConversionException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ServiceConverter extends AbstractConverter
{
    private ElementProcessorFactory $processorFactory;
    private ?WarningCollectorInterface $warningCollector = null;

    public function __construct(?WarningCollectorInterface $warningCollector = null)
    {
        $this->warningCollector = $warningCollector;
        $this->processorFactory = new ElementProcessorFactory($warningCollector);
    }

    public function supports(\DOMDocument $document): bool
    {
        $root = $document->documentElement;

        if ($root->localName !== 'container') {
            return false;
        }

        foreach ($root->attributes as $attr) {
            if (str_contains($attr->value ?? '', 'symfony.com/schema/dic/services')) {
                return true;
            }
        }

        return false;
    }

    public function convert(\DOMDocument $document, string $xmlPath): string
    {
        $this->indentLevel = 0;
        $output = '<?php';

        $output .= $this->nl(0);
        $output .= $this->nl(0).'namespace Symfony\Component\DependencyInjection\Loader\Configurator;';
        $output .= $this->nl(0);
        $output .= $this->nl(0).'return static function(ContainerConfigurator $container) {';

        $this->indentLevel++;

        $hasServices = false;
        $hasParameters = false;
        $whenEnvironments = [];

        foreach ($document->documentElement->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement) {
                if ($childNode->nodeName === 'services') {
                    $hasServices = true;
                } elseif ($childNode->nodeName === 'parameters') {
                    $hasParameters = true;
                } elseif ($childNode->nodeName === 'when') {
                    $env = $childNode->getAttribute('env');
                    if ($env) {
                        $whenEnvironments[] = $env;
                    }
                }
            }
        }

        if ($hasServices) {
            $output .= $this->nl().'$services = $container->services();';
        }
        // Always need parameters for .container.known_envs (added by Symfony's XmlFileLoader)
        if ($hasServices || $hasParameters || !empty($whenEnvironments)) {
            $output .= $this->nl().'$parameters = $container->parameters();';
        }
        
        if ($hasServices || $hasParameters || !empty($whenEnvironments)) {
            $output .= $this->nl(0);
        }

        $this->processorFactory->setIndentLevel($this->indentLevel);

        // Process child elements in specific order for correct parameter ordering
        // 1. Imports
        // 2. Parameters
        // 3. .container.known_envs (if there are when blocks)
        // 4. Services
        // 5. When blocks

        foreach ($document->documentElement->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && $childNode->nodeName === 'imports') {
                $output .= $this->processElement($childNode);
            }
        }

        foreach ($document->documentElement->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && $childNode->nodeName === 'parameters') {
                $output .= $this->processElement($childNode);
            }
        }

        // Generate .container.known_envs parameter only when there are when blocks
        // This breaks unittests with symfony test fixtures but we need this for it to work with our config files
        // Symfony's XmlFileLoader only adds this parameter when <when> blocks are present
        if (!empty($whenEnvironments)) {
            $output .= $this->nl().'$parameters->set(\'.container.known_envs\', [';
            $this->indentLevel++;
            foreach ($whenEnvironments as $env) {
                $output .= $this->nl().'\''.$this->escapeString($env).'\',';
            }
            $this->indentLevel--;
            $output .= $this->nl().']);';
            $output .= $this->nl(0);
        }

        foreach ($document->documentElement->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && $childNode->nodeName === 'services') {
                $output .= $this->processElement($childNode);
            }
        }

        foreach ($document->documentElement->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && $childNode->nodeName === 'when') {
                $output .= $this->processElement($childNode);
            }
        }

        $this->indentLevel--;
        $output .= $this->nl(0).'};';
        $output .= $this->nl(0);

        return $output;
    }

    protected function processElement(\DOMElement $element): string
    {
        return match ($element->nodeName) {
            'imports' => $this->processImports($element),
            'parameters' => $this->processParameters($element),
            'services' => $this->processServices($element),
            'when' => $this->processWhen($element),
            default => '',
        };
    }

    private function processImports(\DOMElement $importsNode): string
    {
        $output = '';
        foreach ($importsNode->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->nodeName === 'import') {
                $resource = $node->getAttribute('resource');
                $ignoreErrors = $this->parseBooleanAttribute($node, 'ignore-errors');

                // Convert .xml extension to .php
                if (str_ends_with($resource, '.xml')) {
                    $resource = substr($resource, 0, -4) . '.php';
                }

                if ($ignoreErrors) {
                    $output .= $this->nl().'$container->import(\''.$resource.'\', null, true);';
                } else {
                    $output .= $this->nl().'$container->import(\''.$resource.'\');';
                }
            }
        }
        if ($output) {
            $output .= $this->nl(0);
        }
        return $output;
    }

    private function processParameters(\DOMElement $parametersNode): string
    {
        $output = '';
        $parameterProcessor = new Elements\ParameterProcessor();
        $parameterProcessor->setIndentLevel($this->indentLevel);
        
        foreach ($parametersNode->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->nodeName === 'parameter') {
                $key = $node->getAttribute('key');
                $value = $parameterProcessor->process($node);
                $output .= $this->nl().'$parameters->set('.$this->formatString($key).', '.$value.');';
            }
        }
        if ($output) {
            $output .= $this->nl(0);
        }
        return $output;
    }


    private function processServices(\DOMElement $servicesNode): string
    {
        $output = '';
        $hasDefaults = false;
        $hasInstanceof = false;

        foreach ($servicesNode->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                switch ($node->nodeName) {
                    case 'defaults':
                        $output .= $this->processDefaults($node);
                        $hasDefaults = true;
                        break;
                    case 'service':
                        if (!$hasDefaults && !$hasInstanceof && $output === '') {
                            // Add spacing before first service if no defaults
                        } elseif (!$hasDefaults && !$hasInstanceof) {
                            $output = $this->nl(0).ltrim($output);
                        }
                        $output .= $this->processService($node);
                        break;
                    case 'alias':
                        $output .= $this->processAlias($node);
                        break;
                    case 'prototype':
                        $output .= $this->processPrototype($node);
                        break;
                    case 'instanceof':
                        $output .= $this->processInstanceof($node);
                        $hasInstanceof = true;
                        break;
                    case 'stack':
                        $output .= $this->processStack($node);
                        break;
                }
            }
        }

        return $output;
    }

    private function processDefaults(\DOMElement $defaultsNode): string
    {
        $output = $this->nl().'$services->defaults()';
        $this->indentLevel++;

        if ($this->parseBooleanAttribute($defaultsNode, 'autowire')) {
            $output .= $this->nl().'->autowire()';
        }
        if ($this->parseBooleanAttribute($defaultsNode, 'autoconfigure')) {
            $output .= $this->nl().'->autoconfigure()';
        }
        if ($this->parseBooleanAttribute($defaultsNode, 'public')) {
            $output .= $this->nl().'->public()';
        }

        $this->processorFactory->setIndentLevel($this->indentLevel);
        foreach ($defaultsNode->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                try {
                    $processor = $this->processorFactory->getProcessor($node);
                    $output .= $processor->process($node);
                } catch (ConversionException $e) {
                    $this->warningCollector?->addWarning(
                        'Skipped unsupported element in defaults',
                        [
                            'element' => $node->nodeName,
                            'reason' => $e->getMessage(),
                        ]
                    );
                }
            }
        }

        $this->indentLevel--;
        $output .= ';';
        $output .= $this->nl(0);

        return $output;
    }

    private function processService(\DOMElement $serviceNode): string
    {
        $id = $serviceNode->getAttribute('id');
        $class = $serviceNode->getAttribute('class');
        $alias = $serviceNode->getAttribute('alias');

        if ($alias) {
            $output = $this->nl().'$services->alias(\''.$this->escapeString($id).'\', \''.$this->escapeString($alias).'\')';

            if ($this->parseBooleanAttribute($serviceNode, 'public')) {
                $output .= '->public()';
            }

            // Handle deprecation for aliases
            foreach ($serviceNode->childNodes as $node) {
                if ($node instanceof \DOMElement && $node->nodeName === 'deprecated') {
                    $package = $node->getAttribute('package');
                    $version = $node->getAttribute('version');
                    $message = $this->getTextContent($node);

                    $deprecateArgs = [];
                    $deprecateArgs[] = "'".$package."'";
                    $deprecateArgs[] = "'".$version."'";
                    $deprecateArgs[] = "'".$this->escapeString($message ?: '')."'";

                    $output .= '->deprecate('.implode(', ', $deprecateArgs).')';
                }
            }
        } else {
            $output = $this->nl().'$services->set(\''.$this->escapeString($id).'\'';

            if ($class) {
                $output .= ', '.$this->convertValue($class);
            }

            $output .= ')';

            $output .= $this->processServiceConfiguration($serviceNode);
        }

        $output .= ';';

        return $output;
    }

    private function processServiceConfiguration(\DOMElement $serviceNode): string
    {
        $output = '';
        $this->indentLevel++;
        $this->processorFactory->setIndentLevel($this->indentLevel);

        // Handle autowire with both true and false cases
        $autowire = $serviceNode->getAttribute('autowire');
        if (in_array($autowire, ['true', '1'], true)) {
            $output .= $this->nl().'->autowire()';
        } elseif (in_array($autowire, ['false', '0'], true)) {
            $output .= $this->nl().'->autowire(false)';
        }

        // Handle autoconfigure with both true and false cases
        $autoconfigure = $serviceNode->getAttribute('autoconfigure');
        if (in_array($autoconfigure, ['true', '1'], true)) {
            $output .= $this->nl().'->autoconfigure()';
        } elseif (in_array($autoconfigure, ['false', '0'], true)) {
            $output .= $this->nl().'->autoconfigure(false)';
        }
        if ($this->parseBooleanAttribute($serviceNode, 'public')) {
            $output .= $this->nl().'->public()';
        }
        if ($this->parseBooleanAttribute($serviceNode, 'private')) {
            $output .= $this->nl().'->private()';
        }
        // Handle lazy attribute - can be boolean or string (interface name)
        if ($serviceNode->hasAttribute('lazy')) {
            $lazy = $serviceNode->getAttribute('lazy');
            if (in_array($lazy, ['true', '1'], true)) {
                $output .= $this->nl().'->lazy()';
            } else {
                $output .= $this->nl().'->lazy('.$this->formatString($lazy).')';
            }
        }
        if ($this->parseBooleanAttribute($serviceNode, 'abstract')) {
            $output .= $this->nl().'->abstract()';
        }
        if ($this->parseBooleanAttribute($serviceNode, 'synthetic')) {
            $output .= $this->nl().'->synthetic()';
        }
        if ($parent = $serviceNode->getAttribute('parent')) {
            $output .= $this->nl().'->parent(\''.$parent.'\')';
        }
        if ($this->parseBooleanAttribute($serviceNode, 'shared', true) === false) {
            $output .= $this->nl().'->share(false)';
        }

        $decorates = $serviceNode->getAttribute('decorates');
        if ($decorates) {
            $decorationInnerName = $serviceNode->getAttribute('decoration-inner-name');
            $decorationPriority = $serviceNode->getAttribute('decoration-priority');
            $decorationOnInvalid = $serviceNode->getAttribute('decoration-on-invalid');

            $decorateArgs = ["'".$decorates."'"];
            if ($decorationInnerName) {
                $decorateArgs[] = "'".$decorationInnerName."'";
            } else {
                $decorateArgs[] = 'null';
            }
            if ($decorationPriority !== '') {
                $decorateArgs[] = (int) $decorationPriority;
            }
            if ($decorationOnInvalid) {
                if (!$decorationPriority) {
                    $decorateArgs[] = '0';
                }
                $decorateArgs[] = ContainerInterface::class.'::'.strtoupper($decorationOnInvalid);
            }

            $output .= $this->nl().'->decorate('.implode(', ', $decorateArgs).')';
        }

        if ($constructor = $serviceNode->getAttribute('constructor')) {
            $output .= $this->nl().'->constructor(\''.$this->escapeString($constructor).'\')';
        }

        foreach ($serviceNode->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->nodeName === 'deprecated') {
                $package = $node->getAttribute('package');
                $version = $node->getAttribute('version');
                $message = $this->getTextContent($node);

                // deprecate() always requires 3 parameters
                $deprecateArgs = [];
                $deprecateArgs[] = "'".$package."'";
                $deprecateArgs[] = "'".$version."'";
                // Message can be empty
                $deprecateArgs[] = "'".$this->escapeString($message ?: '')."'";

                $output .= $this->nl().'->deprecate('.implode(', ', $deprecateArgs).')';
            }
        }

        $arguments = $this->processArguments($serviceNode);
        if (!empty($arguments)) {
            $output .= $this->nl().'->args(['.implode(', ', $arguments).'])';
        }

        foreach ($serviceNode->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                switch ($node->nodeName) {
                    case 'file':
                        $output .= $this->nl().'->file('.$this->formatString($this->getTextContent($node)).')';
                        break;
                    case 'argument':
                    case 'deprecated':
                        // Already handled
                        break;
                    case 'resource-tag':
                        // Handle resource-tag specifically
                        $tagName = $node->getAttribute('name') ?: $node->nodeValue;
                        $output .= $this->nl().'->resourceTag('.$this->formatString($tagName);
                        
                        // Add attributes if present
                        $attributes = [];
                        foreach ($node->attributes as $attrName => $attrNode) {
                            if ($attrName !== 'name') {
                                $attributes[$attrName] = $this->formatValue($attrNode->nodeValue);
                            }
                        }
                        
                        if (!empty($attributes)) {
                            $outputs = [];
                            foreach ($attributes as $key => $value) {
                                $outputs[] = $this->formatString($key) . ' => ' . $value;
                            }
                            $output .= ', ['.implode(', ', $outputs).']';
                        }
                        
                        $output .= ')';
                        break;
                    case 'from-callable':
                        $output .= $this->processFromCallable($node);
                        break;
                    default:
                        try {
                            $processor = $this->processorFactory->getProcessor($node);
                            $output .= $processor->process($node);
                        } catch (ConversionException $e) {
                            $this->warningCollector?->addWarning(
                                'Skipped unsupported element in service configuration',
                                [
                                    'element' => $node->nodeName,
                                    'service' => $serviceNode->getAttribute('id'),
                                    'reason' => $e->getMessage(),
                                ]
                            );
                        }
                }
            }
        }

        $this->indentLevel--;
        return $output;
    }

    private function processArguments(\DOMElement $serviceNode): array
    {
        $argumentProcessor = new ArgumentProcessor();
        $argumentProcessor->setIndentLevel($this->indentLevel);

        /** @var \DOMElement[] $argumentElements */
        $argumentElements = array_filter(iterator_to_array($serviceNode->childNodes), fn(\DOMNode $node) => $node instanceof \DOMElement && $node->nodeName === 'argument');

        if (count($argumentElements) === 0) {
            return [];
        }

        // If there's only one argument, use simple format
        if (count($argumentElements) === 1) {
            $arg = current($argumentElements);
            $key = $arg->getAttribute('key');
            if ($arg->hasAttribute('index')) {
                $key = 'index_'.$arg->getAttribute('index');
            }
            if ($key) {
                return [$this->formatString($key) . ' => ' . $argumentProcessor->process($arg)];
            }

            return [$argumentProcessor->process($arg)];
        }

        // Multiple arguments - preserve keys and format nicely
        $arguments = [];
        foreach ($argumentElements as $arg) {
            $key = $arg->getAttribute('key');
            if ($arg->hasAttribute('index')) {
                $key = $arg->getAttribute('index');
            }
            if ($key) {
                $arguments[] = $this->formatString($key) . ' => ' . $argumentProcessor->process($arg);
            } else {
                $arguments[] = $argumentProcessor->process($arg);
            }
        }

        return $arguments;
    }

    /**
     * Format a string value for PHP output (with quotes)
     */
    private function formatString(string $value): string
    {
        if (class_exists($value) || interface_exists($value) || trait_exists($value) || enum_exists($value)) {
            return '\\'.ltrim($value, '\\') . '::class';
        }

        if (str_ends_with($value, '\\')) {
            $value = addcslashes($value, '\'\\');
        } else {
            $value = addcslashes($value, '\'');
        }

        return "'" . $value . "'";
    }

    /**
     * Format a value for PHP output, detecting type
     */
    private function formatValue(string $value): string
    {
        // Try to detect the value type
        if (strtolower($value) === 'true') {
            return 'true';
        }

        if (strtolower($value) === 'false') {
            return 'false';
        }

        if (strtolower($value) === 'null') {
            return 'null';
        }

        if (is_numeric($value)) {
            return $value;
        }

        // Check if it's a parameter reference
        if (preg_match('/^%(.+)%$/', $value)) {
            return $this->formatString($value);
        }

        // Regular string
        return $this->formatString($value);
    }

    /**
     * Process from-callable element
     */
    private function processFromCallable(\DOMElement $callable): string
    {
        $service = $callable->getAttribute('service');
        $class = $callable->getAttribute('class');
        $method = $callable->getAttribute('method');

        if ($service && $method) {
            // Service::method form
            return $this->nl().'->fromCallable([service('.$this->formatString($service).'), '.$this->formatString($method).'])';
        }

        if ($class && $method) {
            // Class::method form
            return $this->nl().'->fromCallable(['.$this->formatString($class).', '.$this->formatString($method).'])';
        }

        return '';
    }

    private function processAlias(\DOMElement $aliasNode): string
    {
        $id = $aliasNode->getAttribute('id');
        $alias = $aliasNode->getAttribute('alias');

        $output = $this->nl().'$services->alias(\''.$this->escapeString($id).'\', \''.$this->escapeString($alias).'\')';

        if ($this->parseBooleanAttribute($aliasNode, 'public')) {
            $output .= '->public()';
        }

        $output .= ';';

        return $output;
    }

    private function processPrototype(\DOMElement $prototypeNode): string
    {
        $namespace = $prototypeNode->getAttribute('namespace');
        $resource = $prototypeNode->getAttribute('resource');
        $exclude = $prototypeNode->getAttribute('exclude');

        $output = $this->nl().'$services->load(\''.$this->escapeString($namespace).'\', \''.$resource.'\')';

        // Collect all exclude patterns from both attribute and child elements
        $excludes = [];
        if ($exclude) {
            $excludes[] = $exclude;
        }
        
        // Process child <exclude> elements
        foreach ($prototypeNode->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->nodeName === 'exclude') {
                $trimmedValue = trim($node->nodeValue);
                if ($trimmedValue !== '') {
                    $excludes[] = $trimmedValue;
                }
            }
        }

        if (!empty($excludes)) {
            if (count($excludes) === 1) {
                $singleExclude = $excludes[0];
                // Check if it's a single pattern with {a,b,c} notation
                if (strpos($singleExclude, '{') !== false && strpos($singleExclude, '}') !== false) {
                    $output .= $this->nl(1).'->exclude(['.$this->nl(2).'\''.$singleExclude.'\',' . $this->nl(1).'])';
                } else {
                    $output .= '->exclude(\''.$this->escapeString($singleExclude).'\')';
                }
            } else {
                // Multiple exclude patterns
                $this->indentLevel++;
                $output .= $this->nl().'->exclude([';
                $this->indentLevel++;
                foreach ($excludes as $exc) {
                    $output .= $this->nl().'\''.$this->escapeString($exc).'\',';
                }
                $this->indentLevel--;
                $output .= $this->nl().'])';
                $this->indentLevel--;
            }
        }

        $this->indentLevel++;
        $this->processorFactory->setIndentLevel($this->indentLevel);

        foreach ($prototypeNode->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                // Skip exclude elements - already processed above
                if ($node->nodeName === 'exclude') {
                    continue;
                }
                
                try {
                    $processor = $this->processorFactory->getProcessor($node);
                    $output .= $processor->process($node);
                } catch (ConversionException $e) {
                    // Skip unsupported elements
                }
            }
        }

        $this->indentLevel--;
        $output .= ';';
        $output .= $this->nl(0);

        return $output;
    }

    private function processInstanceof(\DOMElement $instanceofNode): string
    {
        $id = $instanceofNode->getAttribute('id');

        $output = $this->nl().'$services->instanceof(\''.$this->escapeString($id).'\')';

        $this->indentLevel++;
        $this->processorFactory->setIndentLevel($this->indentLevel);

        foreach ($instanceofNode->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                try {
                    $processor = $this->processorFactory->getProcessor($node);
                    $output .= $processor->process($node);
                } catch (ConversionException $e) {
                    $this->warningCollector?->addWarning(
                        'Skipped unsupported element in instanceof',
                        [
                            'element' => $node->nodeName,
                            'instanceof' => $id,
                            'reason' => $e->getMessage(),
                        ]
                    );
                }
            }
        }

        $this->indentLevel--;
        $output .= ';';
        $output .= $this->nl(0);

        return $output;
    }

    private function processStack(\DOMElement $stackNode): string
    {
        $id = $stackNode->getAttribute('id');

        $output = $this->nl().'$services->stack(\''.$this->escapeString($id).'\', [';
        $this->indentLevel++;

        $services = [];
        foreach ($stackNode->childNodes as $node) {
            if ($node instanceof \DOMElement && $node->nodeName === 'service') {
                $serviceId = $node->getAttribute('id');
                $class = $node->getAttribute('class');

                if ($serviceId) {
                    $services[] = $this->nl().'service(\''.$serviceId.'\')';
                } else {
                    $inlineOutput = 'inline_service(\''.$class.'\')';

                    $arguments = $this->processArguments($node);
                    if (!empty($arguments)) {
                        $inlineOutput .= '->args(['.implode(', ', $arguments).'])';
                    }

                    $services[] = $this->nl().$inlineOutput;
                }
            }
        }

        $output .= implode(',', $services);
        $this->indentLevel--;
        $output .= $this->nl().']);';

        return $output;
    }

    private function processWhen(\DOMElement $whenNode): string
    {
        $env = $whenNode->getAttribute('env');

        $output = $this->nl().'if ($container->env() === \''.$env.'\') {';
        $this->indentLevel++;

        $output .= $this->processChildNodes($whenNode);

        $this->indentLevel--;
        $output .= $this->nl().'}';
        $output .= $this->nl(0);

        return $output;
    }
}
