<?php

namespace KVStore;

class Response
{
    private int $status_code = 200;
    private ?string $content = null;
    private $content_stream = null;
    private $headers = [
        "Access-Control-Allow-Origin" => "*",
    ];

    public function __construct(int $status_code = null)
    {
        if ($status_code) {
            $this->status_code = $status_code;
        }
    }

    public function statusCode(int $status_code)
    {
        $this->status_code = $status_code;

        return $this;
    }

    public function header(string $name, string $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function setContent(mixed $content, string $content_type = "text/plain", int $status_code = null)
    {
        if ($status_code) {
            $this->status_code = $status_code;
        }

        $this->headers["Content-Type"] = $content_type;

        if (is_string($content)) {
            $this->content = $content;
            return $this;
        }

        if (is_resource($content)) {
            $this->content_stream = $content;
            return $this;
        }

        throw new \Exception("Content must be a string or a resource");
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getContentType()
    {
        return $this->headers["Content-Type"];
    }

    public function text(mixed $content, int $status_code = null)
    {
        if (is_array($content)) {
            // TODO: handle array of objects
            $content = implode("\n", $content);
        } else if (is_object($content)) {
            $lines = [];
            foreach ($content as $key => $value) {
                if (is_object($value)) {
                    $lines[] = '[' . $key . ']';
                } else {
                    $lines[] = $key . "=" . $value;
                }
            }
            $content = implode("\n", $lines);
        }

        return $this->setContent($content, "text/plain", $status_code);
    }

    public function html(mixed $content, int $status_code = null)
    {
        if (is_array($content)) {
            $content = implode("", $content);
        } else if (is_object($content)) {
            $lines = ['<dl>'];
            foreach ($content as $key => $value) {
                if (is_object($value)) {
                    // TODO: Implement
                } else {
                    $lines[] = '<dt>' . $key . "</dt><dd>" . $value . '</dd>';
                }
            }
            $lines[] = '</dl>';
            $content = implode("\n", $lines);
        }

        return $this->setContent($content, "text/html", $status_code);
    }

    public function xml(mixed $content, int $status_code = null)
    {
        $element = xml_encode($content, "object");
        $content = $element->asXML();

        return $this->setContent($content, "text/xml", $status_code);
    }

    public function json(mixed $content, $numeric_check = false, int $status_code = null)
    {
        return $this->setContent(
            json_encode($content, $numeric_check ? JSON_NUMERIC_CHECK : 0),
            "application/json",
            $status_code
        );
    }

    public function csv(mixed $content, array $headers = null, int $status_code = null)
    {
        $lines = [];

        if (!is_array($content)) {
            $content = [$content];
        }

        if (is_null($headers)) {
            $headers = get_csv_headers($content[0]);
        }

        $lines[] = implode(",", $headers);

        foreach ($content as $object) {
            $lines[] = implode(",", get_csv_values($object, $headers));
        }

        return $this->setContent(implode("\n", $lines), "text/csv", $status_code);
    }

    public function autoContent(mixed $content, $request = new Request(), int $status_code = null, string $mime_hint = null)
    {
        if (str_starts_with($mime_hint, "text/")) {
            if ($request->isAccepted("application/json", true)) {
                return $this->json($content, numeric_check: true, status_code: $status_code);
            }

            if ($request->isAccepted("text/plain", true)) {
                return $this->text($content, $status_code);
            }

            if ($request->isAccepted("text/csv", true)) {
                return $this->csv($content, status_code: $status_code);
            }

            if ($request->isAccepted("text/html", true)) {
                return $this->html($content, status_code: $status_code);
            }

            if ($request->isAccepted("text/xml", true)) {
                return $this->xml($content, status_code: $status_code);
            }
        }

        if ($mime_hint === "application/json") {
            return $this->json($content, numeric_check: true, status_code: $status_code);
        }

        if ($mime_hint === "text/plain") {
            return $this->text($content, $status_code);
        }

        if ($mime_hint === "text/csv") {
            return $this->csv($content, status_code: $status_code);
        }

        if ($mime_hint === "text/html") {
            return $this->html($content, status_code: $status_code);
        }

        if (is_object($content) || is_array($content)) {
            if ($request->isAccepted("application/json")) {
                return $this->json($content, numeric_check: true, status_code: $status_code);
            }

            throw new \Exception("No suitable Content-Type match");
        }

        if ($mime_hint) {
            return $this->setContent($content, $mime_hint, $status_code);
        }

        return $this->text($content, status_code: $status_code);
    }

    public function serveFile(string $filename, string $content_type = null, int $status_code = null)
    {
        $project_dir = dirname(__DIR__);
        $real_path = realpath($project_dir . "/" . $filename);

        if (!str_starts_with($real_path, $project_dir)) {
            throw new \Exception("Tried to serve file outside project root");
        }

        if (!$content_type) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext === "html") {
                $content_type = "text/html";
            } else if ($ext === "json") {
                $content_type = "application/json";
            } else if ($ext === "csv") {
                $content_type = "text/csv";
            }
        }

        return $this->setContent(file_get_contents($real_path), $content_type, $status_code);
    }

    public function send()
    {
        $this->sendStatusHeader();

        foreach ($this->headers as $header => $value) {
            header("$header: $value");
        }

        if (is_resource($this->content_stream)) {
            fpassthru($this->content_stream);
        } else {
            echo $this->content;
        }
    }

    private function sendStatusHeader()
    {
        $message = [
            "200" => "OK",
            "201" => "Created",
            "202" => "Accepted",
            "204" => "No Content",
            "400" => "Bad Request",
            "401" => "Unauthorized",
            "403" => "Forbidden",
            "404" => "Not Found",
            "405" => "Method Not Allowed",
            "500" => "Server Error",
            "501" => "Not Implemented",
        ];

        $header = "HTTP/1.1 " . $this->status_code;

        if (isset($message[$this->status_code])) {
            $header .= " " . $message[$this->status_code];
        }

        header($header);
    }
}

function get_csv_headers(mixed $object)
{
    return is_array($object) ? array_keys($object) : (
        is_object($object) ? array_keys((array) $object) : ["value"]
    );
}

function get_csv_values(mixed $object, array $headers)
{
    return array_map(function ($key) use ($object) {
        if (is_array($object)) {
            $val = $object[$key];
        } else if (is_object($object)) {
            $val = $object->$key;
        } else {
            return $object;
        }

        if (!is_string($val)) {
            $val = (string) $val;
        }

        if (str_contains($val, ",")) {
            return "\"" . str_replace("\"", "\\\"", $val) . "\"";
        }

        return $val;
    }, $headers);
}

function xml_encode(mixed $value = null, string $key = "root", \SimpleXMLElement $parent = null)
{
    if (is_object($value))
        $value = (array) $value;
    if (!is_array($value)) {
        if ($parent === null) {
            if (is_numeric($key))
                $key = 'item';
            if ($value === null)
                $node = new \SimpleXMLElement("<$key />");
            else
                $node = new \SimpleXMLElement("<$key>$value</$key>");
        } else {
            $parent->addChild($key, $value);
            $node = $parent;
        }
    } else {
        $array_numeric = false;
        if ($parent === null) {
            if (empty($value))
                $node = new \SimpleXMLElement("<$key />");
            else
                $node = new \SimpleXMLElement("<$key></$key>");
        } else {
            if (!isset($value[0]))
                $node = $parent->addChild($key);
            else {
                $array_numeric = true;
                $node = $parent;
            }
        }
        foreach ($value as $k => $v) {
            if ($array_numeric)
                xml_encode($v, $key, $node);
            else
                xml_encode($v, $k, $node);
        }
    }
    return $node;
}
