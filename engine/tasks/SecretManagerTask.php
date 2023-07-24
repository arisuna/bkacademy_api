<?php

use Dotenv\Dotenv;

class SecretManagerTask extends \Phalcon\Cli\Task
{
    use TaskTrait;

    public function uploadV1Action()
    {
        $path = __DIR__ . "/../../.configuration/env/development/v1";
        $files = array_diff(scandir($path), array('.', '..'));
        $appConfig = $this->getDI()->get('appConfig');
        $sdk = new Aws\Sdk([
            'version' => 'latest',
            'region' => $appConfig->aws->region
        ]);
        $secretManagerClient = $sdk->createSecretsManager();
        foreach ($files as $file) {
            $this->pushFile($path, $file, $secretManagerClient);
        }
    }

    public function uploadV2Action()
    {
        $path = __DIR__ . "/../../.configuration/env/development/v2";
        $files = array_diff(scandir($path), array('.', '..'));
        $appConfig = $this->getDI()->get('appConfig');
        $sdk = new Aws\Sdk([
            'version' => 'latest',
            'region' => $appConfig->aws->region
        ]);
        $secretManagerClient = $sdk->createSecretsManager();
        foreach ($files as $file) {
            $this->pushFile($path, $file, $secretManagerClient);
        }
    }

    public function uploadProductionAction()
    {
        $path = __DIR__ . "/../../.configuration/env/production";
        $files = array_diff(scandir($path), array('.', '..'));
        $appConfig = $this->getDI()->get('appConfig');
        $sdk = new Aws\Sdk([
            'version' => 'latest',
            'region' => $appConfig->aws->region
        ]);
        $secretManagerClient = $sdk->createSecretsManager();
        foreach ($files as $file) {
            $this->pushFile($path, $file, $secretManagerClient);
        }
    }

    public function testFileAction()
    {
        $path = __DIR__ . "/../../.configuration/env/development/v1";
        $file = "preprod.thuydev.env";
        $newDotenv = new Dotenv($path, $file);
        $newDotenv->overload();

        $secretName = getenv('ENV_SECRET_MANAGER_ID');
        $secretString = json_encode($_ENV);

        var_dump($secretString);
    }

    /**
     * @param $path
     * @param $file
     * @param $secretManagerClient
     */
    public function pushFile($path, $file, $secretManagerClient)
    {
        $newDotenv = new Dotenv($path, $file);
        $newDotenv->overload();

        $secretName = getenv('ENV_SECRET_MANAGER_ID');
        $secretString = json_encode($_ENV);

        try {
            $result = $secretManagerClient->putSecretValue([
                'SecretId' => $secretName,
                'SecretString' => $secretString,
            ]);

            echo "[SUCCESS]" . $secretName . "\r\n";
        } catch (\Aws\Exception\AwsException $e) {
            // output error message if fails
            echo $e->getMessage();
            echo "[FAIL]" . $secretName . "\r\n";
        } catch (Exception $e) {
            echo "[FAIL]" . $secretName . "\r\n";
        }
    }

    /**
     * importLocal V2
     */
    public function envLocalV2Action()
    {
        $this->importEnvAction([
            '--name=relotalent/api/local/thinhdev/env/v2',
            '--region=ap-southeast-1'
        ]);
    }

    /**
     * importLocal V1
     */
    public function envLocalV1Action()
    {
        $this->importEnvAction([
            '--name=relotalent/api/local/thinhdev/env/v1',
            '--region=ap-southeast-1'
        ]);
    }

    /**
     * importLocal V1
     */
    public function envPreprodV1Action()
    {
        $this->importEnvAction([
            '--name=relotalent/api/preprod/thinhdev/env/v1',
            '--region=ap-southeast-1'
        ]);
    }

    /**
     * importLocal V1
     */
    public function envPreprodV2Action()
    {
        $this->importEnvAction([
            '--name=relotalent/api/preprod/thinhdev/env/v2',
            '--region=ap-southeast-1'
        ]);
    }

    /**
     *
     */
    public function envProdV1Action()
    {
        $this->importEnvAction([
            '--name=relotalent/api/production/env',
            '--region=ap-southeast-1'
        ]);
    }

    /**
     * @param array $params
     */
    public function importEnvAction($params = [])
    {
        $parseParams = $this->parseParams($params);
        $secretName = isset($parseParams['name']) ? $parseParams['name'] : '';
        $region = isset($parseParams['region']) ? $parseParams['region'] : 'ap-southeast-1';

        if ($secretName != '') {
            $appConfig = $this->getDI()->get('appConfig');
            $options = [
                'version' => 'latest',
                'region' => $region,
            ];
            if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
                $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
            }
            $sdk = new Aws\Sdk($options);


            $secretManagerClient = $sdk->createSecretsManager();
            $secret = '';
            try {
                $result = $secretManagerClient->getSecretValue([
                    'SecretId' => $secretName,
                ]);

            } catch (\Aws\Exception\AwsException $e) {
                $error = $e->getAwsErrorCode();

                echo "[FAIL] error {$e->getMessage()} \r\n";

                if ($error == 'DecryptionFailureException') {
                    // Secrets Manager can't decrypt the protected secret text using the provided AWS KMS key.
                    // Handle the exception here, and/or rethrow as needed.
                    throw $e;
                }
                if ($error == 'InternalServiceErrorException') {
                    // An error occurred on the server side.
                    // Handle the exception here, and/or rethrow as needed.
                    throw $e;
                }
                if ($error == 'InvalidParameterException') {
                    // You provided an invalid value for a parameter.
                    // Handle the exception here, and/or rethrow as needed.
                    throw $e;
                }
                if ($error == 'InvalidRequestException') {
                    // You provided a parameter value that is not valid for the current state of the resource.
                    // Handle the exception here, and/or rethrow as needed.
                    throw $e;
                }
                if ($error == 'ResourceNotFoundException') {
                    // We can't find the resource that you asked for.
                    // Handle the exception here, and/or rethrow as needed.
                    throw $e;
                }
            }
            // Decrypts secret using the associated KMS CMK.
            // Depending on whether the secret is a string or binary, one of these fields will be populated.
            if (isset($result['SecretString'])) {
                $secret = $result['SecretString'];
                $variables = json_decode($secret, true);

                $file = fopen(__DIR__ . "/../../.env", "w+");
                foreach ($variables as $name => $value) {
                    if (is_string($value) && \Reloday\Application\Lib\Helpers::__isStringHasSpace($value)) {
                        fputs($file, $name . "=\"" . $value . "\"\r\n");
                    } else {
                        fputs($file, $name . "=" . $value . "\r\n");
                    }
                }
                fclose($file);
                echo "[SUCCESS] ENV FILE generated successfully \r\n";
            } else {
                var_dump($result);
                echo "[FAIL] SecretName {$secretName} not found \r\n";
            }

            die(__METHOD__);
        }
    }
}