<?php

use \Phan\Issue;

/**
 * This configuration will be read and overlayed on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 *
 * @see src/Phan/Config.php
 * See Config for all configurable options.
 *
 * This is a config file which tests all built in plugins,
 * in addition to testing backwards compatibility checks and dead code detection.
 */
return [
    "target_php_version" => '7.1',

    // If true, missing properties will be created when
    // they are first seen. If false, we'll report an
    // error message.
    "allow_missing_properties" => false,

    // Allow null to be cast as any type and for any
    // type to be cast to null.
    "null_casts_as_any_type" => false,

    // If enabled, scalars (int, float, bool, string, null)
    // are treated as if they can cast to each other.
    'scalar_implicit_cast' => false,

    // If true, seemingly undeclared variables in the global
    // scope will be ignored. This is useful for projects
    // with complicated cross-file globals that you have no
    // hope of fixing.
    'ignore_undeclared_variables_in_global_scope' => false,

    // Backwards Compatibility Checking
    // Check for $$var[] and $foo->$bar['baz'] and Foo::$bar['baz']() and $this->$bar['baz']
    'backward_compatibility_checks' => false,

    // If enabled, check all methods that override a
    // parent method to make sure its signature is
    // compatible with the parent's. This check
    // can add quite a bit of time to the analysis.
    'analyze_signature_compatibility' => true,

    // Test dead code detection
    'dead_code_detection' => true,

    'globals_type_map' => ['test_global_exception' => 'Exception', 'test_global_error' => '\\Error'],

    "quick_mode" => false,

    'generic_types_enabled' => true,

    'minimum_severity' => Issue::SEVERITY_LOW,

    'directory_list' => ['src'],

    'analyzed_file_extensions' => ['php'],

    // A list of plugin files to execute
    // (Execute all of them.)
    // FooName is shorthand for /path/to/phan/.phan/plugins/FooName.php.
    'plugins' => [
        __DIR__ . '/../../../.phan/plugins/AlwaysReturnPlugin.php',  // This is testing the plugin locator, use old syntax
        '../../.phan/plugins/DemoPlugin.php',  // Test behavior of the plugin locator.
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'InvalidVariableIssetPlugin',
        'NonBoolBranchPlugin',
        'NonBoolInLogicalArithPlugin',
        'NumericalComparisonPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'UnreachableCodePlugin',
        'UnusedSuppressionPlugin',
    ],
];
