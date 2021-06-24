<?php
/*
 * This file is a part of "comely-io/mailer" package.
 * https://github.com/comely-io/mailer
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/mailer/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Mailer\Message;

use Comely\Mailer\Exception\EmailMessageException;

/**
 * Class Attachment
 * @package Comely\Mailer\Message
 */
class Attachment
{
    /** @var string */
    private string $path;
    /** @var string */
    private string $name;
    /** @var string|null */
    private ?string $type = null;
    /** @var null|string */
    private ?string $id = null;
    /** @var string */
    private string $disposition;

    /**
     * Attachment constructor.
     * @param string $filePath
     * @param string|null $type
     * @throws EmailMessageException
     */
    public function __construct(string $filePath, ?string $type = null)
    {
        // Check if file exists and is readable
        if (!is_readable($filePath)) {
            throw EmailMessageException::attachmentUnreadable($filePath);
        }

        $this->path = $filePath; // Save file path
        $this->type = $type; // Content type (if specified)
        $this->name = basename($this->path);
        $this->id = null;
        $this->disposition = "attachment";

        // Check if content type is not explicit
        if (!$this->type) {
            // Check if "fileinfo" extension is loaded
            if (extension_loaded("fileinfo")) {
                $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
                $this->type = $fileInfo->file($this->path);
            } else {
                trigger_error(
                    'Recommend "fileinfo" extension for Attachments with Comely Mailer component',
                    E_USER_NOTICE
                );
            }

            if (!$this->type) {
                $this->type = self::fileType($this->name);
            }
        }
    }

    /**
     * @param string $name
     * @return Attachment
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $id
     * @return Attachment
     */
    public function contentId(string $id): self
    {
        $this->id = $id;
        $this->disposition = "inline";
        return $this;
    }

    /**
     * @param string $disposition
     * @return Attachment
     */
    public function disposition(string $disposition): self
    {
        $this->disposition = $disposition;
        return $this;
    }

    /**
     * @return array
     * @throws EmailMessageException
     */
    public function mime(): array
    {
        $read = file_get_contents($this->path);
        if (!$read) {
            throw EmailMessageException::attachmentUnreadable($this->path);
        }

        $mime[] = sprintf('Content-Type: %1$s; name="%2$s"', $this->type, $this->name);
        $mime[] = "Content-Transfer-Encoding: base64";
        $mime[] = sprintf('Content-Disposition: %1$s', $this->disposition);
        if ($this->id) {
            $mime[] = sprintf('Content-ID: <%1$s>', $this->id);
        }

        $mime[] = chunk_split(base64_encode($read));

        return $mime;
    }

    /**
     * Get suggested content type from file extension, defaults to "octet-stream"
     *
     * @param string $fileName
     * @return string
     */
    public static function fileType(string $fileName): string
    {
        return match (pathinfo($fileName, PATHINFO_EXTENSION)) {
            "txt" => "text/plain",
            "zip" => "application/zip",
            "tar" => "application/x-tar",
            "pdf" => "application/pdf",
            "psd" => "image/vnd.adobe.photoshop",
            "swf" => "application/x-shockwave-flash",
            "odt" => "application/vnd.oasis.opendocument.text",
            "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "doc" => "application/msword",
            "avi" => "video/x-msvideo",
            "mp4" => "video/mp4",
            "jpeg", "jpg" => "image/jpeg",
            "png" => "image/png",
            "gif" => "image/gif",
            "svg" => "image/svg+xml",
            default => "application/octet-stream",
        };
    }
}
