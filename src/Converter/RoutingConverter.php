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

use GromNaN\SymfonyConfigXmlToPhp\Converter\Elements\CollectionProcessor;

class RoutingConverter extends AbstractConverter
{
    private CollectionProcessor $collectionProcessor;

    public function __construct()
    {
        $this->collectionProcessor = new CollectionProcessor();
    }
    public function supports(\DOMDocument $document): bool
    {
        $root = $document->documentElement;

        if ($root->localName !== 'routes') {
            return false;
        }

        foreach ($root->attributes as $attr) {
            if (str_contains($attr->value ?? '', 'symfony.com/schema/routing')) {
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
        $output .= $this->nl(0).'use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;';
        $output .= $this->nl(0);
        $output .= $this->nl(0).'return function (RoutingConfigurator $routes) {';

        $this->indentLevel++;

        $output .= $this->processChildNodes($document->documentElement);

        $this->indentLevel--;
        $output .= $this->nl().'};';
        $output .= $this->nl(0);

        return $output;
    }

    protected function processElement(\DOMElement $element): string
    {
        return match ($element->nodeName) {
            'route' => $this->processRoute($element),
            'import' => $this->processImport($element),
            'when' => $this->processWhen($element),
            default => '',
        };
    }

    private function processRoute(\DOMElement $routeNode): string
    {
        $id = $routeNode->getAttribute('id');
        $path = $routeNode->getAttribute('path');
        $controller = $routeNode->getAttribute('controller');

        $output = $this->nl().'$routes->add(\''.$id.'\', \''.$path.'\')';

        $this->indentLevel++;

        if ($controller) {
            $output .= $this->nl().'->controller(\''.$controller.'\')';
        }

        $methods = $routeNode->getAttribute('methods');
        if ($methods) {
            $methodArray = array_map('trim', explode(',', $methods));
            $output .= $this->nl().'->methods('.$this->convertValue($methodArray).')';
        }

        $host = $routeNode->getAttribute('host');
        if ($host) {
            $output .= $this->nl().'->host(\''.$host.'\')';
        }

        $schemes = $routeNode->getAttribute('schemes');
        if ($schemes) {
            $schemeArray = array_map('trim', explode(',', $schemes));
            $output .= $this->nl().'->schemes('.$this->convertValue($schemeArray).')';
        }

        $priority = $routeNode->getAttribute('priority');
        if ($priority !== '') {
            $output .= $this->nl().'->priority('.$priority.')';
        }

        $locale = $routeNode->getAttribute('locale');
        if ($locale) {
            $output .= $this->nl().'->locale(\''.$locale.'\')';
        }

        $format = $routeNode->getAttribute('format');
        if ($format) {
            $output .= $this->nl().'->format(\''.$format.'\')';
        }

        if ($this->parseBooleanAttribute($routeNode, 'stateless')) {
            $output .= $this->nl().'->stateless()';
        }

        $this->collectionProcessor->setIndentLevel($this->indentLevel);

        $requirements = $this->collectionProcessor->processRequirementsAsArray($routeNode);
        if (!empty($requirements)) {
            $output .= $this->nl().'->requirements('.$this->convertValue($requirements).')';
        }

        $defaults = $this->collectionProcessor->processDefaultsAsArray($routeNode);
        if (!empty($defaults)) {
            $output .= $this->nl().'->defaults('.$this->convertValue($defaults).')';
        }

        $options = $this->collectionProcessor->processOptionsAsArray($routeNode);
        if (!empty($options)) {
            $output .= $this->nl().'->options('.$this->convertValue($options).')';
        }

        $condition = $routeNode->getAttribute('condition');
        if (!$condition) {
            foreach ($routeNode->childNodes as $node) {
                if ($node instanceof \DOMElement && $node->nodeName === 'condition') {
                    $condition = $this->getTextContent($node);
                    break;
                }
            }
        }
        if ($condition) {
            $output .= $this->nl().'->condition(\''.addslashes($condition).'\')';
        }

        $this->indentLevel--;
        $output .= ';';
        $output .= $this->nl(0);

        return $output;
    }

    private function processImport(\DOMElement $importNode): string
    {
        $resource = $importNode->getAttribute('resource');
        $prefix = $importNode->getAttribute('prefix');
        $type = $importNode->getAttribute('type');

        $output = $this->nl().'$routes->import(\''.$resource.'\'';

        if ($type) {
            $output .= ', \''.$type.'\'';
        } elseif ($prefix) {
            $output .= ', null';
        }

        $output .= ')';

        $this->indentLevel++;

        if ($prefix) {
            $output .= $this->nl().'->prefix(\''.$prefix.'\')';
        }

        $namePrefix = $importNode->getAttribute('name-prefix');
        if ($namePrefix) {
            $output .= $this->nl().'->namePrefix(\''.$namePrefix.'\')';
        }

        $host = $importNode->getAttribute('host');
        if ($host) {
            $output .= $this->nl().'->host(\''.$host.'\')';
        }

        if ($this->parseBooleanAttribute($importNode, 'trailing-slash-on-root')) {
            $output .= $this->nl().'->trailingSlashOnRoot()';
        }

        $this->collectionProcessor->setIndentLevel($this->indentLevel);

        $requirements = $this->collectionProcessor->processRequirementsAsArray($importNode);
        if (!empty($requirements)) {
            $output .= $this->nl().'->requirements('.$this->convertValue($requirements).')';
        }

        $defaults = $this->collectionProcessor->processDefaultsAsArray($importNode);
        if (!empty($defaults)) {
            $output .= $this->nl().'->defaults('.$this->convertValue($defaults).')';
        }

        $options = $this->collectionProcessor->processOptionsAsArray($importNode);
        if (!empty($options)) {
            $output .= $this->nl().'->options('.$this->convertValue($options).')';
        }

        $this->indentLevel--;
        $output .= ';';
        $output .= $this->nl(0);

        return $output;
    }

    private function processWhen(\DOMElement $whenNode): string
    {
        $env = $whenNode->getAttribute('env');

        $output = $this->nl().'$routes->when(\''.$env.'\')';
        $this->indentLevel++;

        $output .= $this->processChildNodes($whenNode);

        $this->indentLevel--;
        $output .= ';';
        $output .= $this->nl(0);

        return $output;
    }
}
