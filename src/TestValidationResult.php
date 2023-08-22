<?php

declare(strict_types=1);

namespace Wulfheart\LaravelTestFormRequest;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertContains;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;

final class TestValidationResult
{
    private Validator $validator;
    private ?ValidationException $failed;

    public function __construct(Validator $validator, ?ValidationException $failed = null)
    {
        $this->validator = $validator;
        $this->failed = $failed;
    }

    public function assertPassesValidation()
    {
        assertTrue($this->validator->passes(),
            sprintf("Validation of the payload:\n%s\ndid not pass validation rules\n%s\n",
                json_encode($this->validator->getData(), JSON_PRETTY_PRINT),
                json_encode($this->getFailedRules(), JSON_PRETTY_PRINT)
            )
        );

        return $this;
    }

    public function assertFailsValidation($expectedFailedRules = [])
    {
        assertTrue($this->validator->fails());

        if (empty($expectedFailedRules)) {
            return $this;
        }

        $failedRules = $this->getFailedRules();

        foreach ($expectedFailedRules as $expectedFailedRule => $constraints) {
            assertArrayHasKey($expectedFailedRule, $failedRules);
            assertStringContainsString($constraints, $failedRules[$expectedFailedRule]);
        }

        return $this;
    }

    public function assertHasMessage($message, $rule = null)
    {
        $validationMessages = $this->getValidationMessages($rule);
        assertContains($message, $validationMessages,
            sprintf("\"%s\" was not contained in the failed%s validation messages\n%s",
                $message, $rule ? ' '.$rule : '', json_encode($validationMessages, JSON_PRETTY_PRINT)
            )
        );

        return $this;
    }


    public function getFailedRules()
    {
        if (!$this->failed) {
            return [];
        }

        $failedRules = collect($this->validator->failed())
            ->map(function ($details) {
                return collect($details)->reduce(function ($aggregateRule, $constraints, $ruleName) {
                    $failedRule = Str::lower($ruleName);

                    if (count($constraints)) {
                        $failedRule .= ':'.implode(',', $constraints);
                    }

                    return $aggregateRule.$failedRule;
                });
            });

        return $failedRules;
    }

    private function getValidationMessages($rule = null)
    {
        $messages = $this->validator->messages()->getMessages();
        if ($rule) {
            return $messages[$rule] ?? [];
        }

        return Arr::flatten($messages);
    }
}