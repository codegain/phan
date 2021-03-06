<?php declare(strict_types=1);

namespace Phan\Tests\Language\Element;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Comment;
use Phan\Language\Type;
use Phan\Language\Type\StaticType;
use Phan\Library\None;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Tests\BaseTest;

/**
 * Unit tests of Comment
 * @phan-file-suppress PhanThrowTypeAbsentForCall
 */
final class CommentTest extends BaseTest
{
    /** @var CodeBase The code base within which we're operating */
    protected $code_base;

    /** @var array<string,mixed> the old values of Phan's Config. */
    protected $old_values = [];

    const OVERRIDES = [
        'read_type_annotations' => true,
        'read_magic_property_annotations' => true,
        'read_magic_method_annotations' => true,
    ];

    protected function setUp() : void
    {
        Phan::setIssueCollector(new BufferingCollector());
        $this->code_base = new CodeBase([], [], [], [], []);
        foreach (self::OVERRIDES as $key => $value) {
            $this->old_values[$key] = Config::getValue($key);
            Config::setValue($key, $value);
        }
    }

    /**
     * @suppress PhanTypeMismatchProperty
     */
    protected function tearDown() : void
    {
        $this->code_base = null;
        foreach ($this->old_values as $key => $value) {
            Config::setValue($key, $value);
        }
    }

    public function testEmptyComment() : void
    {
        $comment = Comment::fromStringInContext(
            '/** foo */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertFalse($comment->isDeprecated());
        $this->assertFalse($comment->isOverrideIntended());
        $this->assertFalse($comment->isNSInternal());
        $this->assertFalse($comment->hasReturnUnionType());
        $this->assertFalse($comment->hasReturnUnionType());
        $this->assertInstanceOf(None::class, $comment->getClosureScopeOption());
        $this->assertSame([], $comment->getParameterList());
        $this->assertSame([], $comment->getParameterMap());
        $this->assertSame([], $comment->getSuppressIssueSet());
        $this->assertFalse($comment->hasParameterWithNameOrOffset('bar', 0));
        $this->assertSame([], $comment->getVariableList());
    }

    public function testGetParameterMap() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @param int $myParam */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['myParam'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['myParam'];
        $this->assertSame('int $myParam', (string)$my_param_doc);
        $this->assertFalse($my_param_doc->isOptional());
        $this->assertTrue($my_param_doc->isRequired());
        $this->assertFalse($my_param_doc->isVariadic());
        $this->assertSame('myParam', $my_param_doc->getName());
        $this->assertFalse($my_param_doc->isOutputReference());
    }

    public function testGetParameterMapReferenceIgnored() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @param int &$myParam */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['myParam'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['myParam'];
        $this->assertSame('int $myParam', (string)$my_param_doc);
        $this->assertFalse($my_param_doc->isOptional());
        $this->assertTrue($my_param_doc->isRequired());
        $this->assertFalse($my_param_doc->isVariadic());
        $this->assertSame('myParam', $my_param_doc->getName());
        $this->assertFalse($my_param_doc->isOutputReference());
    }

    public function testGetVariadicParameterMap() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @param int|string ...$args */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['args'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['args'];
        $this->assertSame('int|string ...$args', (string)$my_param_doc);
        $this->assertTrue($my_param_doc->isOptional());
        $this->assertFalse($my_param_doc->isRequired());
        $this->assertTrue($my_param_doc->isVariadic());
        $this->assertSame('args', $my_param_doc->getName());
        $this->assertFalse($my_param_doc->isOutputReference());
    }

    public function testGetOutputParameter() : void
    {
        $comment = Comment::fromStringInContext(
            "/** @param int|string \$args @phan-output-reference\n@param string \$other*/",
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );

        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['args', 'other'], \array_keys($parameter_map));
        $this->assertTrue($parameter_map['args']->isOutputReference());
        $this->assertFalse($parameter_map['other']->isOutputReference());
    }


    public function testGetReturnType() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @return int|string */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertTrue($comment->hasReturnUnionType());
        $return_type = $comment->getReturnType();
        $this->assertSame('int|string', (string)$return_type);
    }

    public function testGetReturnTypeThis() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @return $this */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertTrue($comment->hasReturnUnionType());
        $return_type = $comment->getReturnType();
        $this->assertSame('static', (string)$return_type);
        $this->assertTrue($return_type->hasType(StaticType::instance(false)));
    }

    public function testGetMagicProperty() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @property int|string   $myProp */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_CLASS
        );
        $this->assertTrue($comment->hasMagicPropertyWithName('myProp'));
        $property = $comment->getMagicPropertyMap()['myProp'];
        $this->assertSame('int|string $myProp', (string)$property);
    }

    public function testGetMagicMethod() : void
    {
        $comment_text = <<<'EOT'
/**
 * @method static int|string my_method(int $x, stdClass ...$rest) description
 * @method myInstanceMethod2(int, $other = 'myString') description
 */
EOT;
        $comment = Comment::fromStringInContext(
            $comment_text,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_CLASS
        );
        $method_map = $comment->getMagicMethodMap();
        $this->assertSame(['my_method', 'myInstanceMethod2'], \array_keys($method_map));
        $method_definition = $method_map['my_method'];
        $this->assertSame('static function my_method(int $x, \stdClass ...$rest) : int|string', (string)$method_definition);
        $this->assertSame('my_method', $method_definition->getName());
        $instance_method_definition = $method_map['myInstanceMethod2'];
        $this->assertSame('function myInstanceMethod2(int $p1, $other = default) : void', (string)$instance_method_definition);
        $this->assertSame('myInstanceMethod2', $instance_method_definition->getName());
    }

    public function testGetTemplateType() : void
    {
        $comment_text = <<<'EOT'
/**
 * The check for template is case-sensitive.
 * @template T1
 * @Template TestIgnored
 * @template u
 */
EOT;
        $comment = Comment::fromStringInContext(
            $comment_text,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_CLASS
        );
        $template_types = $comment->getTemplateTypeList();
        $this->assertCount(2, $template_types);
        $t1_info = $template_types[0];
        $this->assertSame('T1', $t1_info->getName());
        $u_info = $template_types[1];
        $this->assertSame('u', $u_info->getName());
    }

    public function testGetParameterArrayNew() : void
    {
        // Currently, we ignore the array key. This may change in a future release.
        $comment_text = <<<'EOT'
/**
 * @param array<mixed, string> $myParam
 * @param array<string , stdClass> ...$rest
 */
EOT;
        $comment = Comment::fromStringInContext(
            $comment_text,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $parameter_map = $comment->getParameterMap();
        $this->assertSame(['myParam', 'rest'], \array_keys($parameter_map));
        $this->assertSame([], $comment->getParameterList());
        $my_param_doc = $parameter_map['myParam'];
        $this->assertSame('string[] $myParam', (string)$my_param_doc);
        $this->assertFalse($my_param_doc->isOptional());
        $this->assertTrue($my_param_doc->isRequired());
        $this->assertFalse($my_param_doc->isVariadic());
        $this->assertSame('myParam', $my_param_doc->getName());

        // Argument #2, #3, etc. passed by callers are arrays of stdClasses
        $rest_doc = $parameter_map['rest'];
        $this->assertSame('array<string,\stdClass> ...$rest', (string)$rest_doc);
        $this->assertTrue($rest_doc->isOptional());
        $this->assertFalse($rest_doc->isRequired());
        $this->assertTrue($rest_doc->isVariadic());
        $this->assertSame('rest', $rest_doc->getName());
    }

    public function testGetVarArrayNew() : void
    {
        // Currently, we ignore the array key. This may change in a future release.
        $comment_text = <<<'EOT'
/**
 * @var int $my_int
 * @var array<string , stdClass> $array
 * @var float (Unparsable)
 */
EOT;
        $comment = Comment::fromStringInContext(
            $comment_text,
            $this->code_base,
            new Context(),
            1,
            Comment::ON_METHOD
        );
        $this->assertSame([], $comment->getParameterMap());
        $this->assertSame([], $comment->getParameterList());
        $var_map = $comment->getVariableList();
        $this->assertSame([0, 1], \array_keys($var_map));
        $my_int_doc = $var_map[0];
        $this->assertSame('int $my_int', (string)$my_int_doc);
        $this->assertSame('my_int', $my_int_doc->getName());

        $array_doc = $var_map[1];
        $this->assertSame('array<string,\stdClass> $array', (string)$array_doc);
        $this->assertSame('array', $array_doc->getName());
    }

    public function testGetClosureScope() : void
    {
        $comment = Comment::fromStringInContext(
            '/** @phan-closure-scope MyNS\MyClass */',
            $this->code_base,
            new Context(),
            1,
            Comment::ON_FUNCTION  // ON_CLOSURE doesn't exist yet.
        );
        $scope_option = $comment->getClosureScopeOption();
        $this->assertTrue($scope_option->isDefined());
        $scope_type = $scope_option->get();
        $expected_type = Type::fromFullyQualifiedString('MyNS\MyClass');
        $this->assertSame($expected_type, $scope_type);
        $this->assertSame($expected_type, $scope_type);
    }

    public function testParseReturnCommentCallableString() : void
    {
        // @phan-suppress-next-line PhanAccessClassConstantInternal
        \preg_match(\Phan\Language\Element\Comment\Builder::RETURN_COMMENT_REGEX, '/** @return callable-string description */', $matches);
        $this->assertSame('@return callable-string', $matches[0]);
    }

    public function testParseSuppressCommentString() : void
    {
        // @phan-suppress-next-line PhanAccessClassConstantInternal
        \preg_match(\Phan\Language\Element\Comment\Builder::PHAN_SUPPRESS_REGEX, '/** @suppress MyPlugin-string description */', $matches);
        $this->assertSame('MyPlugin-string', $matches[1]);

        // @phan-suppress-next-line PhanAccessClassConstantInternal
        \preg_match(\Phan\Language\Element\Comment\Builder::PHAN_SUPPRESS_REGEX, '/** @suppress MyPlugin_Issue- description of why this was suppressed */', $matches);
        $this->assertSame('MyPlugin_Issue', $matches[1]);

        // @phan-suppress-next-line PhanAccessClassConstantInternal
        \preg_match(\Phan\Language\Element\Comment\Builder::PHAN_SUPPRESS_REGEX, '/** @suppress MyPlugin--description of why this was suppressed */', $matches);
        $this->assertSame('MyPlugin', $matches[1]);

        // @phan-suppress-next-line PhanAccessClassConstantInternal
        \preg_match(\Phan\Language\Element\Comment\Builder::PHAN_SUPPRESS_REGEX, '/** @suppress MyPluginIssue, MyOtherPlugin-Issue--description of why this was suppressed */', $matches);
        $this->assertSame('MyPluginIssue, MyOtherPlugin-Issue', $matches[1]);
    }
}
