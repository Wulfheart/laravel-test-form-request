<?php

namespace Wulfheart\LaravelTestFormRequest;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\ParameterBag;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

/**
 * @template T
 */
class TestFormRequest
{
    private FormRequest $request;

    /**
     * @param T $request
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * @return T
     */
    public function getBaseRequest()
    {
        return $this->request;
    }

    /**
     * @return TestValidationResult<T>
     */
    public function validate(array $data)
    {
        $this->request->request = new ParameterBag($data);

        /** @var Validator $validator */
        $validator = Closure::fromCallable(function () {
            return $this->getValidatorInstance();
        })->call($this->request);

        try {
            $validator->validate();
        } catch (ValidationException $e) {
            return new TestValidationResult($validator, $e);
        }

        return new TestValidationResult($validator, $this->request);
    }

    public function by(Authenticatable $user = null)
    {
        $this->request->setUserResolver(fn () => $user);

        return $this;
    }

    public function withParams(array $params)
    {
        foreach ($params as $param => $value) {
            $this->withParam($param, $value);
        }

        return $this;
    }

    /**
    * @return self<T>
     */
    public function withParam(string $param, $value)
    {
        $this->request->route()->setParameter($param, $value);

        return $this;
    }

    public function assertAuthorized()
    {
        assertTrue(
            $this->bully(fn () => $this->passesAuthorization(), $this->request),
            'The provided user is not authorized by this request'
        );
    }

    public function assertNotAuthorized()
    {
        assertFalse(
            $this->bully(fn () => $this->passesAuthorization(), $this->request),
            'The provided user is authorized by this request'
        );
    }

    private function bully(Closure $elevatedFunction, object $targetObject)
    {
        return Closure::fromCallable($elevatedFunction)->call($targetObject);
    }
}
