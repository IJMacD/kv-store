<?php

namespace KVStore;

class Response
{
    private $status_code = 200;
    private $content = null;
    private $headers = [
        "Access-Control-Allow-Origin" => "*",
    ];

    public function __construct($status_code = null)
    {
        if ($status_code) {
            $this->status_code = $status_code;
        }
    }

    public function statusCode($status_code)
    {
        $this->status_code = $status_code;

        return $this;
    }

    public function header($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function setContent($content, $content_type, $status_code = null)
    {
        if ($status_code) {
            $this->status_code = $status_code;
        }

        $this->headers["Content-Type"] = $content_type;

        $this->content = $content;

        return $this;
    }

    public function text($content, $status_code = null)
    {
        if (is_array($content)) {
            // TODO: handle array of objects
            $content = implode("\n", $content);
        } else if (is_object($content)) {
            // TODO: implement
            $content = "[Object]";
        }
        return $this->setContent($content, "text/plain", $status_code);
    }

    public function html($content, $status_code = null)
    {
        if (is_array($content)) {
            $content = implode("", $content);
        }
        return $this->setContent($content, "text/html", $status_code);
    }

    public function json($content, $numeric_check = false, $status_code = null)
    {
        return $this->setContent(
            json_encode($content, $numeric_check ? JSON_NUMERIC_CHECK : 0),
            "application/json",
            $status_code
        );
    }

    public function csv($content, $headers = null, $status_code = null)
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

    public function autoContent($content, $request = new Request(), $status_code = null)
    {
        if ($request->isAccepted("text/plain", true)) {
            return $this->text($content, $status_code);
        }

        if ($request->isAccepted("text/csv", true)) {
            return $this->csv($content, status_code: $status_code);
        }

        return $this->json($content, $status_code);
    }

    public function serveFile($filename, $content_type = null, $status_code = null)
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

        echo $this->content;
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

function get_csv_headers($object)
{
    return is_array($object) ? array_keys($object) : (
        is_object($object) ? array_keys((array)$object) : ["value"]
    );
}

function get_csv_values($object, $headers)
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
            $val = (string)$val;
        }

        if (str_contains($val, ",")) {
            return "\"" . str_replace("\"", "\\\"", $val) . "\"";
        }

        return $val;
    }, $headers);
}
