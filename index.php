<!DOCTYPE html>
<html lang="en">

<head>
    <title>Azure Computer Vision</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
</head>

<body>
    <div class="header">
        <a class="logo">Analisis Gambar dengan Computer Vision</a>
    </div>

    <div class="container">
        <div class="input-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-25-input">
                        <label for="namaproduk">Pilih gambar yang akan dianalisis</label>
                    </div>
                    <div class="col-75-input">
                        <input type="file" name="file" accept="image/*">
                    </div>
                    <div class="row">
                        <input type="submit" name="submit" value="Upload dan analisis">
                    </div>
                </div>
            </form>
        </div>

        <?php
        require_once 'vendor/autoload.php';
        require_once "./random_string.php";

        use MicrosoftAzure\Storage\Blob\BlobRestProxy;
        use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
        use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
        use MicrosoftAzure\Storage\Blob\Models\ListContainersOptions;
        use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
        use MicrosoftAzure\Storage\Blob\Models\PublicAccessType;

        $connectionString = "DefaultEndpointsProtocol=https;AccountName=<account_name>;AccountKey=<account_key>;EndpointSuffix=core.windows.net";

        // Create blob client.
        $blobClient = BlobRestProxy::createBlobService($connectionString);
        $containerName = "imageanalysis";

        if (isset($_POST["submit"])) {

            if (!empty($_FILES["file"]["name"])) {
                $blobImage = "";
                $fileToUpload = $_FILES["file"]["name"];

                $createContainerOptions = new CreateContainerOptions();
                $createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);

                // Set container metadata.
                $createContainerOptions->addMetaData("key1", "value1");
                $createContainerOptions->addMetaData("key2", "value2");

                // See if the container already exists.
                $listContainersOptions = new ListContainersOptions;
                $listContainersOptions->setPrefix($containerName);
                $listContainersResult = $blobClient->listContainers($listContainersOptions);
                $containerExists = false;
                foreach ($listContainersResult->getContainers() as $container) {
                    if ($container->getName() == $containerName) {
                        $containerExists = true;
                        break;
                    }
                }
                if (!$containerExists) {
                    $blobClient->createContainer($containerName, $createContainerOptions);
                }


                try {

                    $tmp = explode('.', $fileToUpload);
                    $file_extension = end($tmp);

                    $file_name = (basename($fileToUpload, "." . $file_extension) . PHP_EOL);
                    $file_name_replace_whitespaces = preg_replace('/\s+/', '', $file_name);
                    $new_file_name = $file_name_replace_whitespaces . "_" . generateRandomString() . "." . $file_extension;

                    $content = fopen($_FILES["file"]["tmp_name"], "r");


                    // //Upload blob
                    $blobClient->createBlockBlob($containerName, $new_file_name, $content);

                    // List blobs.
                    $listBlobsOptions = new ListBlobsOptions();
                    $listBlobsOptions->setPrefix($new_file_name);

                    echo "<h2><b>Hasil gambar:</b></h2>";

                    do {
                        $result = $blobClient->listBlobs($containerName, $listBlobsOptions);
                        foreach ($result->getBlobs() as $blob) {
                            $blobImage = $blob->getUrl();
                        }

                        $listBlobsOptions->setContinuationToken($result->getContinuationToken());
                    } while ($result->getContinuationToken());
                    echo "<br />";

                    $imageData = base64_encode(file_get_contents($blobImage));
                    echo '<img width = "40%" src="data:image/jpeg;base64,' . $imageData . '">';

                } catch (ServiceException $e) {
                    $code = $e->getCode();
                    $error_message = $e->getMessage();
                    echo $code . ": " . $error_message . "<br />";
                } catch (InvalidArgumentTypeException $e) {
                    $code = $e->getCode();
                    $error_message = $e->getMessage();
                    echo $code . ": " . $error_message . "<br />";
                }


                if (!empty($blobImage)) {
                    echo '<script type="text/javascript">processAnalysisImage()</script>';
                } else {
                    echo '<script type="text/javascript">alert("Gagal menganalisis")</script>';
                }

                echo "<h2>Hasil analisis gambar:</h2>";

                echo '<p style="font-size:24px" id="responseAnalisis"></p>';
            } else {
                echo '<script type="text/javascript">alert("Silahkan pilih gambar terlebih dahulu")</script>';
            }
        }

        ?>
    </div>
</body>

</html>

<script type="text/javascript">
    window.onload = function processAnalysisImage() {

        var subscriptionKey = "<subscription_key>";

        var uriBase =
            "<uri_base_cognitive_services>/vision/v2.0/analyze";

        // Request parameters.
        var params = {
            "visualFeatures": "Description",
            "details": "",
            "language": "en",
        };

        var sourceImageUrl = "<?php echo $blobImage ?>";

        // Make the REST API call.
        $.ajax({
                url: uriBase + "?" + $.param(params),

                // Request headers.
                beforeSend: function(xhrObj) {
                    xhrObj.setRequestHeader("Content-Type", "application/json");
                    xhrObj.setRequestHeader(
                        "Ocp-Apim-Subscription-Key", subscriptionKey);
                },

                type: "POST",

                // Request body.
                data: '{"url": ' + '"' + sourceImageUrl + '"}',
            })

            .done(function(data) {
                // Show result.
                document.getElementById("responseAnalisis").innerHTML = JSON.parse(JSON.stringify(data.description.captions[0].text, null, 2));
            })

            .fail(function(jqXHR, textStatus, errorThrown) {
                // Display error message.
                var errorString = (errorThrown === "") ? "Error. " :
                    errorThrown + " (" + jqXHR.status + "): ";
                errorString += (jqXHR.responseText === "") ? "" :
                    jQuery.parseJSON(jqXHR.responseText).message;
                alert(errorString);
            });
    };
</script>