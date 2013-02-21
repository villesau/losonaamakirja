<?php

namespace Losofacebook\Service;
use Doctrine\DBAL\Connection;
use Imagick;
use ImagickPixel;
use Symfony\Component\HttpFoundation\Response;
use Losofacebook\Image;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Image service
 */
class ImageService
{
    const COMPRESSION_TYPE = Imagick::COMPRESSION_JPEG;

    /**
     * @var Connection
     */
    private $conn;



    /**
     * @param $basePath
     */
    public function __construct(Connection $conn, $basePath)
    {
        $this->conn = $conn;
        $this->basePath = $basePath;
    }

    /**
     * Creates image
     *
     * @param string $path
     * @param int $type
     * @return integer
     */
    public function createImage($path, $type)
    {
        $this->conn->insert(
            'image',
            [
                'upload_path' => $path,
                'type' => $type
            ]
        );
        $id = $this->conn->lastInsertId();

        $img = new Imagick($path);
        $img->setbackgroundcolor(new ImagickPixel('white'));
        $img = $img->flattenImages();

        $img->setImageFormat("jpeg");

        $img->setImageCompression(self::COMPRESSION_TYPE);
        $img->setImageCompressionQuality(90);
        $img->scaleImage(1200, 1200, true);
        $img->writeImage($this->basePath . '/' . $id);

        if ($type == Image::TYPE_PERSON) {
            $this->createVersions($id);
        } else {
            $this->createCorporateVersions($id);
        }
        return $id;
    }


    public function createCorporateVersions($id)
    {
        $img = new Imagick($this->basePath . '/' . $id);
        $img->thumbnailimage(450, 450, true);

        $geo = $img->getImageGeometry();

        $x = (500 - $geo['width']) / 2;
        $y = (500 - $geo['height']) / 2;
        $key = 'thumb';
                    $versionPath = $this->basePath . '/' . $id . '-' . $key;
        $image = new Imagick($this->basePath . '/' . $id);
        $image->newImage(500, 500, new ImagickPixel('white'));
        $image->setImageFormat('jpeg');
        $image->compositeImage($img, $img->getImageCompose(), $x, $y);

        $thumb = clone $image;
        $thumb->cropThumbnailimage(500, 500);
    
        $thumb->setImageCompression(self::COMPRESSION_TYPE);
        $thumb->setImageCompressionQuality(90);
        $thumb->resizeimage(360, 360, imagick::COLOR_CYAN  , 1);
        $thumb->writeImage($this->basePath . '/' . $id . '-thumb');
        
         $linkPath = realpath($this->basePath . '/../../../web/images')
                    . '/' . $id . '-' . $key . '.jpg';
                        
            if (!is_link($linkPath)) {
                symlink($versionPath, $linkPath);
            } 
    }

        protected function getImageVersions()
    {
        return [
            'thumb' => [
                360,
                90
            ],
            '75x75' => [
                75,
                90
            ],
            '50x50' => [
                50,
                70
            ],
           '20x20' => [
               20,
                70
            ],
            '260x260' => [
                260,
                90
            ],
 
 
        ];
    }
    public function createVersions($id)
    {
       $img = new Imagick($this->basePath . '/' . $id);
        
        foreach ($this->getImageVersions() as $key => $data) {
            
            list($size, $cq) = $data;
            
            $versionPath = $this->basePath . '/' . $id . '-' . $key;
            
            $v = clone $img;
            $v->stripImage();
            $v->cropThumbnailimage($size, $size);
            $v->setImageCompression(self::COMPRESSION_TYPE);
            $v->setInterlaceScheme(Imagick::INTERLACE_PLANE);
            $v->setImageCompressionQuality($cq);
            $v->writeImage($versionPath);
            
            $linkPath = realpath($this->basePath . '/../../../web/images')
                    . '/' . $id . '-' . $key . '.jpg';
                        
            if (!is_link($linkPath)) {
                symlink($versionPath, $linkPath);
            }            
            
        }           

    }

    public function getImageResponse($id, $version = null)
    {
        $path = $this->basePath . '/' . $id;

        if ($version) {
            $path .= '-' . $version;
        }

        if (!is_readable($path)) {
            throw new NotFoundHttpException('Image not found');
        }

        $response = new Response();
        $response->setContent(file_get_contents($path));
        $response->headers->set('Content-type', 'image/jpeg');
        return $response;
    }


}
