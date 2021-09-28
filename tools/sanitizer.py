import pymongo
from pymongo import UpdateOne
import hashlib
import bcrypt
from pymongo import collection


# ssh -L 27017:localhost:27017 user@remoteServer

myclient = pymongo.MongoClient("mongodb://localhost:27017/")


def hashName(hashType):
    if(hashType == 0):
        return "MD5"
    elif(hashType == 1):
        return "SHA1"
    elif(hashType == 2):
        return "SHA256"
    elif(hashType == 3):
        return "SHA384"
    elif(hashType == 4):
        return "SHA512"
    else:
        return None


def hashFunction(hashType, prehash):
    if(hashType == 0):
        return hashlib.md5(prehash)
    elif(hashType == 1):
        return hashlib.sha1(prehash)
    elif(hashType == 2):
        return hashlib.sha256(prehash)
    elif(hashType == 3):
        return hashlib.sha384(prehash)
    elif(hashType == 4):
        return hashlib.sha512(prehash)
    else:
        return None



for hashType in range(0, 5):
    count = 0
    mydb = myclient[hashName(hashType)]
    for firstChar in "0123456789abcdef":
        collection = mydb[firstChar]
        cursor = collection.find()
        for document in cursor:

            count = count + 1
            if(count % 1000000 == 0):
                print(str(count/1000000) + "M")

            hashed = document["_id"]
            prehash = document["password"]
            Hashlib = hashFunction(hashType, prehash.encode()).hexdigest()
            if(hashed != Hashlib):
                if(prehash.startswith('$HEX[') and prehash.endswith(']')):
                    prehash = prehash.split('[')[1].split(']')[0]
                    #print(prehash)
                    prehash = bytes.fromhex(prehash)
                    Hashlib = hashFunction(hashType, prehash).hexdigest()
                    if(hashed != Hashlib):
                        print(str(document) + Hashlib)
                else:
                    if(prehash.startswith(":")):
                        prehash = prehash.split(':', 1)[1]
                        Hashlib = hashFunction(hashType, prehash.encode()).hexdigest()
                        if(hashed == Hashlib):
                            filter = { '_id': hashed }
                            newvalues = { "$set": { 'password': prehash } }
                            collection.update_one(filter, newvalues) 
                            #print('FIXED: ' + str(document))
                        else:
                            if(prehash.startswith('$HEX[') and prehash.endswith(']')):
                                prehashBytes = bytes.fromhex(prehash.split('[')[1].split(']')[0])
                                Hashlib = hashFunction(hashType, prehashBytes).hexdigest()
                                if(hashed == Hashlib):
                                    filter = { '_id': hashed }
                                    newvalues = { "$set": { 'password': prehash } }
                                    collection.update_one(filter, newvalues) 
                                    #print('FIXED: ' + str(document))
                                else:
                                    print('NOT FIXED: ' + str(document))
                            else:
                                print('UNKNOWN 2: ' + str(document))
                    else:
                        print('UNKNOWN: ' + str(document))
            #print(document)
