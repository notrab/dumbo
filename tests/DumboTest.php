<?php

use PHPUnit\Framework\TestCase;
use Dumbo\Dumbo;

class DumboTest extends TestCase
{
    public function testEnvironmentDetection()
    {
        $testCases = [
            // Test case: No environment set
            [
                "serverVars" => [],
                "getenvFunc" => function () {
                    return false;
                },
                "expectedEnv" => Dumbo::ENV_DEVELOPMENT,
            ],
            // Test case: Environment set in $_SERVER
            [
                "serverVars" => ["DUMBO_ENV" => "production"],
                "getenvFunc" => function () {
                    return false;
                },
                "expectedEnv" => Dumbo::ENV_PRODUCTION,
            ],
            // Test case: Environment set via getenv
            [
                "serverVars" => [],
                "getenvFunc" => function () {
                    return "testing";
                },
                "expectedEnv" => Dumbo::ENV_TESTING,
            ],
            // Test case: Invalid environment defaults to development
            [
                "serverVars" => ["DUMBO_ENV" => "invalid"],
                "getenvFunc" => function () {
                    return false;
                },
                "expectedEnv" => Dumbo::ENV_DEVELOPMENT,
            ],
            // Test case: $_SERVER takes precedence over getenv
            [
                "serverVars" => ["DUMBO_ENV" => "production"],
                "getenvFunc" => function () {
                    return "development";
                },
                "expectedEnv" => Dumbo::ENV_PRODUCTION,
            ],
        ];

        foreach ($testCases as $index => $testCase) {
            $app = new Dumbo();
            $app->detectEnvironment(
                $testCase["serverVars"],
                $testCase["getenvFunc"]
            );

            $this->assertEquals(
                $testCase["expectedEnv"],
                $app->getEnvironment(),
                "Failed assertion for test case $index"
            );
        }
    }

    public function testErrorReportingConfiguration()
    {
        $app = new Dumbo();
        $app->detectEnvironment(["DUMBO_ENV" => "production"]);
        $this->assertEquals(0, error_reporting());
        $this->assertEquals("0", ini_get("display_errors"));

        $app = new Dumbo();
        $app->detectEnvironment(["DUMBO_ENV" => "development"]);
        $this->assertEquals(E_ALL, error_reporting());
        $this->assertEquals("1", ini_get("display_errors"));
    }
}
