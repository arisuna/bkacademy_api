<?php

namespace SMXD\Application\Aws\AwsCognito;

class AwsCognitoResult
{
    /**
     * @var array
     */
    private $keys = [];

    /**
     * JWKSet constructor.
     *
     * @param JWK[] $keys
     */
    public function __construct(array $keys)
    {
        foreach ($keys as $k => $v) {
            if(isset($v['Name']) && $v['Value']){
                $this->keys[$v['Name']] = $v['Value'];
            }
        }
    }

    /**
     * Returns the key with the given index. Throws an exception if the index is not present in the key store.
     *
     * @param int|string $index
     *
     * @return JWK
     */
    public function get($index)
    {
        if (!$this->has($index)) {
            return null;
        }
        return $this->keys[$index];
    }

    /**
     * Returns true if the key set contains a key with the given index.
     *
     * @param int|string $index
     *
     * @return bool
     */
    public function has($index): bool
    {
        return array_key_exists($index, $this->keys);
    }
}
