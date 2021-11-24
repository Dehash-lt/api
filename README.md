# Dehash.lt API

You can use our API without any limitations.


# HTTP URL Arguments
| Argument | Default Value | Comment                                    |
| :---     |     :---:     |                                       ---: |
| &json=   |       0       | Enable printing full data in json          |
| &fast=   |       0       | Enable fast lookup (No external search)    |

# Examples

### GET Request:
```bash
$ curl "https://api.dehash.lt/api.php?search=e10adc3949ba59abbe56e057f20f883e"
e10adc3949ba59abbe56e057f20f883e:123456
````

### POST Request:  
Note - POST request size limited to 10MB and every line should be same type.
```bash
$ cat md5.txt
e10adc3949ba59abbe56e057f20f883e
25f9e794323b453885f5181f1b624d0b

$ curl -X POST --data-binary @./md5.txt "https://api.dehash.lt/api.php"
25f9e794323b453885f5181f1b624d0b:123456789
e10adc3949ba59abbe56e057f20f883e:123456
```


### JSON Output:
If you prefer getting JSON format instead of hashcat style format add `&json=1` to the URL

```bash
$ curl "https://api.dehash.lt/api.php?search=e10adc3949ba59abbe56e057f20f883e&json=1"
{
    "http:\/\/nitrxgen.net": {
        "results": [
            "e10adc3949ba59abbe56e057f20f883e:123456"
        ]
    }
}
```
```bash
$ cat md5.txt
e10adc3949ba59abbe56e057f20f883e
25f9e794323b453885f5181f1b624d0b

$ curl -X POST --data-binary @./md5.txt "https://api.dehash.lt/api.php?json=1"
{
    "https:\/\/dehash.lt": {
        "results": [
            "25f9e794323b453885f5181f1b624d0b:123456789"
        ]
    },
    "http:\/\/nitrxgen.net": {
        "results": [
            "e10adc3949ba59abbe56e057f20f883e:123456"
        ]
    }
}
