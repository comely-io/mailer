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
    public readonly string $name;
    /** @var string */
    public readonly string $contentType;

    /**
     * @param string $filePath
     * @param string|null $name
     * @param string|null $contentType
     * @param string $disposition
     * @param string|null $contentId
     * @throws \Comely\Mailer\Exception\EmailMessageException
     */
    public function __construct(
        public readonly string  $filePath,
        ?string                 $name = null,
        ?string                 $contentType = null,
        public readonly string  $disposition = "attachment",
        public readonly ?string $contentId = null
    )
    {
        // Check if file exists and is readable
        if (!is_file($this->filePath) || !is_readable($filePath)) {
            throw EmailMessageException::attachmentUnreadable($filePath);
        }

        if (!$contentType) {
            // Check if "fileinfo" extension is loaded
            if (extension_loaded("fileinfo")) {
                $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
                $contentType = $fileInfo->file($this->filePath);
            } else {
                trigger_error(
                    'Recommend "fileinfo" extension for Attachments with Comely Mailer component',
                    E_USER_NOTICE
                );
            }

            if (!$contentType) {
                $contentType = self::fileType(basename($this->filePath));
            }
        }

        $this->name = $name ?? basename($this->filePath);
        $this->contentType = $contentType;
    }

    /**
     * @return array
     * @throws EmailMessageException
     */
    public function mime(): array
    {
        $read = file_get_contents($this->filePath);
        if (!$read) {
            throw EmailMessageException::attachmentUnreadable($this->filePath);
        }

        $mime[] = sprintf('Content-Type: %1$s; name="%2$s"', $this->contentType, $this->name);
        $mime[] = "Content-Transfer-Encoding: base64";
        $mime[] = sprintf('Content-Disposition: %1$s', $this->disposition);
        if ($this->contentId) {
            $mime[] = sprintf('Content-ID: <%1$s>', $this->contentId);
        }

        $mime[] = chunk_split(base64_encode($read));

        return $mime;
    }

    /**
     * Get suggested content type from file extension, defaults to "octet-stream"
     *
     * @param string $basename
     * @return string
     */
    public static function fileType(string $basename): string
    {
        return match (pathinfo($basename, PATHINFO_EXTENSION)) {
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
