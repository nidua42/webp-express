<?php

namespace WebPExpress;

class SelfTestRedirectToConverter
{

    /**
     * Run test for either jpeg or png
     *
     * @param  array   $config
     * @param  string  $imageType  ("jpeg" or "png")
     * @return array   [$success, $result, $createdTestFiles]
     */
    private static function runTestForImageType($config, $imageType)
    {
        $result = [];
        $createdTestFiles = false;
        $noWarningsYet = true;

        // Copy test image (jpeg)
        list($subResult, $success, $sourceFileName) = SelfTestHelper::copyTestImageToUploadFolder($imageType);
        $result = array_merge($result, $subResult);
        if (!$success) {
            $result[] = 'The test cannot be completed';
            return [false, $result, $createdTestFiles];
        }
        $createdTestFiles = true;

        $requestUrl = Paths::getUploadUrl() . '/' . $sourceFileName;
        $result[] = '## Lets check that browsers supporting webp gets a freshly converted WEBP ' .
            'when the ' . $imageType . ' is requested';
        $result[] = 'Making a HTTP request for the test image (pretending to be a client that supports webp, by setting the "Accept" header to "image/webp")';
        $requestArgs = [
            'headers' => [
                'ACCEPT' => 'image/webp'
            ]
        ];
        list($success, $errors, $headers) = SelfTestHelper::remoteGet($requestUrl, $requestArgs);

        if (!$success) {
            $result[count($result) - 1] .= '. FAILED';
            $result = array_merge($result, $errors);
            $result[] = 'The test cannot be completed';
            //$result[count($result) - 1] .= '. FAILED';
            return [false, $result, $createdTestFiles];
        }
        //$result[count($result) - 1] .= '. ok!';
        $result[] = '*' . $requestUrl . '*';

        $result = array_merge($result, SelfTestHelper::printHeaders($headers));

        if (!isset($headers['content-type'])) {
            $result[] = 'Bummer. There is no "content-type" response header. The test FAILED';
            return [false, $result, $createdTestFiles];
        }

        if ($headers['content-type'] == 'image/' . $imageType) {
            $result[] = 'Bummer. As the "content-type" header reveals, we got the ' . $imageType . '.';
            $result[] = 'The test **failed**{: .error}.';
            $result[] = 'Now, what went wrong?';

            if (isset($headers['x-webp-convert-log'])) {
                //$result[] = 'Inspect the "x-webp-convert-log" headers above, and you ' .
                //    'should have your answer (it is probably because you do not have any conversion methods working).';
                if (SelfTestHelper::hasHeaderContaining($headers, 'x-webp-convert-log', 'Performing fail action: original')) {
                    $result[] = 'The answer lies in the "x-convert-log" response headers: ' .
                        '**The conversion failed**{: .error}. ';
                }
            } else {
                $result[] = 'Well, there is indication that the redirection isnt working. ' .
                    'The PHP script should set "x-webp-convert-log" response headers, but there are none. ';
                    'While these headers could have been eaten in a Cloudflare-like setup, the problem is ';
                    'probably that the redirection simply failed';

                    $result[] = '## Diagnosing redirection problems';
                    $result = array_merge($result, SelfTestHelper::diagnoseFailedRewrite($config));
            }
            return [false, $result, $createdTestFiles];
        }

        if ($headers['content-type'] != 'image/webp') {
            $result[] = 'However. As the "content-type" header reveals, we did not get a webp' .
                'Surprisingly we got: "' . $headers['content-type'] . '"';
            $result[] = 'The test FAILED.';
            return [false, $result, $createdTestFiles];
        }

        $result[] = 'Alrighty. We got a webp, and we got it from the PHP script. **Great!**{: .ok}';

        if (!SelfTestHelper::hasVaryAcceptHeader($headers)) {
            $result[count($result) - 1] .= '. **BUT!**';
            $result[] = '**Warning: We did not receive a Vary:Accept header. ' .
                'That header should be set in order to tell proxies that the response varies depending on the ' .
                'Accept header. Otherwise browsers not supporting webp might get a cached webp and vice versa.**{: .warn}';
            $noWarningsYet = false;
        }
        if (!SelfTestHelper::hasCacheControlOrExpiresHeader($headers)) {
            $result[] = '**Notice: No cache-control or expires header has been set. ' .
                'It is recommended to do so. Set it nice and big once you are sure the webps have a good quality/compression comprimise.**{: .warn}';
        }
        $result[] = '';


        // Check browsers NOT supporting webp
        // -----------------------------------
        $result[] = '## Now lets check that browsers *not* supporting webp gets the ' . strtoupper($imageType);
        $result[] = 'Making a HTTP request for the test image (without setting the "Accept" header)';
        list($success, $errors, $headers) = SelfTestHelper::remoteGet($requestUrl);

        if (!$success) {
            $result[count($result) - 1] .= '. FAILED';
            $result = array_merge($result, $errors);
            $result[] = 'The test cannot be completed';
            //$result[count($result) - 1] .= '. FAILED';
            return [false, $result, $createdTestFiles];
        }
        //$result[count($result) - 1] .= '. ok!';
        $result[] = '*' . $requestUrl . '*';

        $result = array_merge($result, SelfTestHelper::printHeaders($headers));

        if (!isset($headers['content-type'])) {
            $result[] = 'Bummer. There is no "content-type" response header. The test FAILED';
            return [false, $result, $createdTestFiles];
        }

        if ($headers['content-type'] == 'image/webp') {
            $result[] = '**Bummer**{: .error}. As the "content-type" header reveals, we got the webp. ' .
                'So even browsers not supporting webp gets webp. Not good!';
            $result[] = 'The test FAILED.';

            $result[] = '## What to do now?';
            // TODO: We could examine the headers for common CDN responses

            $result[] = 'First, examine the response headers above. Is there any indication that ' .
                 'the image is returned from a CDN cache? ' .
            $result[] = 'If there is: Check out the ' .
                 '*How do I configure my CDN in “Varied image responses” operation mode?* section in the FAQ ' .
                 '(https://wordpress.org/plugins/webp-express/)';

            if (PlatformInfo::isApache()) {
                $result[] = 'If not: please report this in the forum, as it seems the .htaccess rules ';
                $result[] = 'just arent working on your system.';
            } elseif (PlatformInfo::isNginx()) {
                 $result[] = 'Also, as you are on Nginx, check out the ' .
                     ' "I am on Nginx" section in the FAQ (https://wordpress.org/plugins/webp-express/)';
            } else {
                $result[] = 'If not: please report this in the forum, as it seems that there is something ' .
                    'in the *.htaccess* rules generated by WebP Express that are not working.';
            }

            $result[] = '## System info (for manual diagnosing):';
            $result = array_merge($result, SelfTestHelper::allInfo($config));


            return [false, $result, $createdTestFiles];
        }

        if ($headers['content-type'] != 'image/' . $imageType) {
            $result[] = 'Bummer. As the "content-type" header reveals, we did not get the ' . $imageType .
                'Surprisingly we got: "' . $headers['content-type'] . '"';
            $result[] = 'The test FAILED.';
            return [false, $result, $createdTestFiles];
        }
        $result[] = 'Alrighty. We got the ' . $imageType . '. **Great!**{: .ok}.';

        if (!SelfTestHelper::hasVaryAcceptHeader($headers)) {
            $result[count($result) - 1] .= '. **BUT!**';
            $result[] = '**We did not receive a Vary:Accept header. ' .
                'That header should be set in order to tell proxies that the response varies depending on the ' .
                'Accept header. Otherwise browsers not supporting webp might get a cached webp and vice versa.**{: .warn}';
            $noWarningsYet = false;
        }

        return [$noWarningsYet, $result, $createdTestFiles];
    }

    private static function doRunTest($config)
    {
        $result = [];
        $result[] = '# Testing redirection to converter';

        $createdTestFiles = false;
        if (!file_exists(Paths::getConfigFileName())) {
            $result[] = 'Hold on. You need to save options before you can run this test. There is no config file yet.';
            return [true, $result, $createdTestFiles];
        }

        if (!$config['enable-redirection-to-converter']) {
            $result[] = 'Turned off, nothing to test';
            return [true, $result, $createdTestFiles];
        }

        if ($config['image-types'] == 0) {
            $result[] = 'No image types have been activated, nothing to test';
            return [true, $result, $createdTestFiles];
        }

        if ($config['image-types'] & 1) {
            list($success, $subResult, $createdTestFiles) = self::runTestForImageType($config, 'jpeg');
            $result = array_merge($result, $subResult);

            if ($success) {
                if ($config['image-types'] & 2) {
                    $result[] = '## Performing same tests for PNG';
                    list($success, $subResult, $createdTestFiles2) = self::runTestForImageType($config, 'png');
                    $createdTestFiles = $createdTestFiles || $createdTestFiles2;
                    if ($success) {
                        //$result[count($result) - 1] .= '. **ok**{: .ok}';
                        $result[] .= 'All tests passed for PNG as well.';
                        $result[] = '(I shall spare you for the report, which is almost identical to the one above)';
                    } else {
                        $result = array_merge($result, $subResult);
                    }
                }
            }
        } else {
            list($success, $subResult, $createdTestFiles) = self::runTestForImageType($config, 'png');
            $result = array_merge($result, $subResult);
        }

        if ($success) {
            $result[] = '## Conclusion';
            $result[] = 'Everything **seems to work**{: .ok} as it should. ' .
                'However, notice that this test only tested an image which was placed in the *uploads* folder. ' .
                'The rest of the image roots (such as theme images) have not been tested (it is on the TODO). ' .
                'Also on the TODO: If one image type is disabled, check that it does not redirect to the conversion script. ' .
                'These things probably work, though.';
        }


        return [true, $result, $createdTestFiles];
    }

    private static function cleanUpTestImages($config)
    {

        // Clean up test images in upload folder
        SelfTestHelper::deleteTestImagesInUploadFolder();

        // Clean up dummy webp images in cache folder for uploads
        $uploadCacheDir = Paths::getCacheDirForImageRoot(
            $config['destination-folder'],
            $config['destination-structure'],
            'uploads'
        );
        SelfTestHelper::deleteFilesInDir($uploadCacheDir, 'webp-express-test-image-*');

    }

    public static function runTest()
    {
        $config = Config::loadConfigAndFix(false);

        self::cleanUpTestImages($config);

        // Run the actual test
        list($success, $result, $createdTestFiles) = self::doRunTest($config);

        // Clean up test images again. We are very tidy around here
        if ($createdTestFiles) {
            $result[] = 'Deleting test images';
            self::cleanUpTestImages($config);
        }

        return [$success, $result];
    }

}