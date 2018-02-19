<?php

namespace AwsApi;

use Aws\Ses\SesClient;

class Factory
{
    protected $credentials;

    const FROM_ADDRESS = "<Email>";

    public function getCredentials($account = "<account>")
    {
        switch ($account) {
            case "<account>":
                return [
                    'key'    => "<account>",
                    'secret' => "<account>",
                ];
    }

    public function getS3Client($account = "<account>", $region = "region", $version = "latest")
    {
        $client = new \Aws\S3\S3Client([
            "version"       =>  $version,
            "region"        =>  $region,
            "credentials"   =>  $this->getCredentials($account),
        ]);

        return $client;
    }

    public function getEc2Client($account = "<account>", $region = "region", $version = "latest")
    {
        $client = new \Aws\Ec2\Ec2Client([
            "version"       =>  $version,
            "region"        =>  $region,
            "credentials"   =>  $this->getCredentials($account),
        ]);

        return $client;
    }

    public function getCloudFrontClient($account = "<account>", $region = "region", $version = "latest")
    {
        $client = new \Aws\CloudFront\CloudFrontClient([
            "version"       =>  $version,
            "region"        =>  $region,
            "credentials"   =>  $this->getCredentials($account),
        ]);

        return $client;
    }

    public function sendSesEmail ($to, $subject, $body, $account = "<account>", $region = "region", $version = "latest")
    {
        $client = SesClient::factory(array(
            "version"       =>  $version,
            "region"        =>  $region,
            "credentials"   =>  $this->getCredentials($account),
        ));

        $request = [];
        $request["Source"] = self::FROM_ADDRESS;
        $request["Destination"]["ToAddresses"] = [$to];
        $request["Message"]["Subject"]["Data"] = $subject;
        $request["Message"]["Body"]["Text"]["Data"] = $body;

        try {
             $result = $client->sendEmail($request);
             $messageId = $result->get("MessageId");
             echo("Email sent! Message ID: $messageId"."\n");

        } catch (Exception $e) {
             echo("The email was not sent. Error message: ");
             echo($e->getMessage()."\n");
        }
    }

    public function sendSesEmailWithAttachment ($to, $subject, $body, $attachment, $account = "<account>", $region = "region", $version = "latest") 
    {
        $client = SesClient::factory(array(
            "version"       =>  $version,
            "region"        =>  $region,
            "credentials"   =>  $this->getCredentials($account),
        ));

        $filename = explode("/", $attachment);

        $message = "To: ". $to ."\n";
        $message .= "From: ". self::FROM_ADDRESS ."\n";
        $message .= "Subject: " . $subject . "\n";
        $message .= "MIME-Version: 1.0\n";
        $message .= "Content-Type: multipart/mixed; boundary=\"aRandomString_with_signs_or_9879497q8w7r8number\"";
        $message .= "\n\n";
        $message .= "--aRandomString_with_signs_or_9879497q8w7r8number\n";
        $message .= "Content-Type: text/plain; charset=\"utf-8\"";
        $message .= "\n";
        $message .= "Content-Transfer-Encoding: 7bit\n";
        $message .= "Content-Disposition: inline\n";
        $message .= "\n";
        $message .= $body . "\n\n";
        $message .= "\n\n";
        $message .= "--aRandomString_with_signs_or_9879497q8w7r8number\n";
        $message .= "Content-ID: \<77987_SOME_WEIRD_TOKEN_BUT_UNIQUE_SO_SOMETIMES_A_@domain.com_IS_ADDED\>\n";
        $message .= "Content-Type: application/zip; name=\"shell.zip\"";
        $message .= "\n";
        $message .= "Content-Transfer-Encoding: base64\n";
        $message .= "Content-Disposition: attachment; filename=\"" . end($filename) . "\"";
        $message .= "\n";
        $message .= base64_encode(file_get_contents($attachment));
        $message .= "\n";
        $message .= "--aRandomString_with_signs_or_9879497q8w7r8number--\n";

        $request["RawMessage"]["Data"]         = $message;
        $request["RawMessage"]["Source"]       = self::FROM_ADDRESS;
        $request["RawMessage"]["Destinations"] = [$to];

        try {
            $result = $client->sendRawEmail($request);
            $messageId = $result->get("MessageId");
            echo("Email sent! Message ID: $messageId"."\n");
        } catch (Exception $e) {
            echo("The email was not sent. Error message: ");
            echo($e->getMessage()."\n");
        }
    }

    public function uploadToS3($client, $bucket, $pathTo, $pathFrom, $publicRead = 0)
    {
        
        $commands = [
            "Bucket"       => $bucket,
            "Key"          => $pathTo,
            "SourceFile"   => $pathFrom,
        ];

        if ($publicRead == 1) {
            $commands = array_merge($commands, ["ACL"     =>  "public-read"]);
        }

        $client->putObject($commands);

        echo "File " . $pathFrom . " to bucket " . $bucket . " and the path is " . $pathTo . "\n";
    }

    public function checkObjectExistsOnS3($client, $bucket, $pathFrom, $throwException = 1)
    {

        $checkFile = $client->doesObjectExist($bucket, $pathFrom);
        if (!$checkFile && $throwException) {
            throw new \Exception("object " . $pathFrom . " does not exist within bucket " . $bucket);
        } else {
            return $checkFile;
        }
    }

    public function retrieveFileFromS3($client, $bucket, $pathFrom, $saveAs)
    {

        $this->checkObjectExistsOnS3($client, $bucket, $pathFrom);

        $client->getObject([
            "Bucket"       => $bucket,
            "Key"          => $pathFrom,
            "SaveAs"       => $saveAs,
        ]);

        echo "File " . $pathFrom . " has been saved as " . $saveAs . "\n";
    }


    public function copyObjectOnS3($client, $bucket, $pathTo, $pathFrom, $publicRead = 0)
    {

        $this->checkObjectExistsOnS3($client, $bucket, $pathFrom);

        $commands = [
            "Bucket"            => $bucket,
            "Key"               => $pathTo,
            "CopySource"        => $bucket . "/" . $pathFrom,
        ];

        if ($publicRead == 1) {
            $commands = array_merge($commands, ["ACL"     =>  "public-read"]);
        }

        $client->copyObject($commands);


        echo "File has been copied from " . $pathFrom . " from bucket " . $bucket . " to " . $pathTo . "\n";
    }

    public function deleteObjectOnS3($client, $bucket, $pathFrom)
    {

        $this->checkObjectExistsOnS3($client, $bucket, $pathFrom);

        $client->deleteObject([
            "Bucket"            => $bucket,
            "Key"               => $pathFrom,
        ]);

        echo "File has been deleted  from " . $pathFrom . " from bucket " . $bucket . "\n";
    }

    public function retrieveListOfObjectsFromS3($client, $bucket, $pathFrom)
    {
        return $client->getIterator("ListObjects", [
            "Bucket"            => $bucket,
            "Prefix"            => $pathFrom,
        ]);
    }
}
