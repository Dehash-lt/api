# Secure Password Checker API
Our API and database system provides an option to securely check your password in our database WITHOUT revealing it to us.  
You may ask how is this possible, its quite simple:
  
## STEP 1: Hash your password with SHA1 and ONLY take 6 characters:

```bash
$ echo -n "verysecurepassword" | sha1sum 
671bfaf52c98e9bc7092450b5244c325e4df69ee  -

$ echo -n "verysecurepassword" | sha1sum | cut -b1-6
671bfa
```
  
## STEP 2: Take these 6 characters and query our API
You'll receive several hundreds of hashes starting with your string.  
All these hashes are succesfully cracked and stored in our database

```bash
$ curl -i "https://api.dehash.lt/api2.php?search=671bfa" 2>/dev/null | grep 671bfaf52c98e9bc7092450b5244c325e4df69ee
671bfaf52c98e9bc7092450b5244c325e4df69ee
```
  
## STEP 3: Write your own script or use one of our examples writen in:
Python:
```python
import requests
import hashlib


hashedSHA1 = hashlib.sha1(input("Enter password: ").encode()).hexdigest()
hashedSHA1_6chars = hashedSHA1[0:6]

response = requests.get('https://api.dehash.lt/api2.php?search=' + hashedSHA1_6chars).content.decode()
if(response.find(hashedSHA1) == -1):
    print("Password not found in Dehash.lt SHA1 database.")
else:
    print("Password is FOUND in Dehash.lt SHA1 database.")
```
