import pymongo
from pymongo import UpdateOne
import hashlib
import bcrypt
from pymongo import collection


# ssh -L 27017:localhost:27017 user@remoteServer
myclient = pymongo.MongoClient("mongodb://localhost:27017/")



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




total = 0
hashType = 1

for firstChar in "0123456789abcdef":

    if(hashType == 0):
        mydb = myclient["MD5"]
    elif(hashType == 1):
        mydb = myclient["SHA1"]
    elif(hashType == 2):
        mydb = myclient["SHA256"]
    elif(hashType == 3):
        mydb = myclient["SHA384"]
    elif(hashType == 4):
        mydb = myclient["SHA512"]

    collection = mydb[firstChar]

    with open("rockyou2021.txt", errors='ignore') as file_in:
        insertData = []

        i = 0
        for prehash in file_in:
            prehash = prehash.replace("\n", "").replace("\r", "")
            hashed = hashFunction(hashType, prehash.encode()).hexdigest()
            if(hashed.startswith(firstChar)):

                # Double check and fix
                if(prehash.startswith(':$HEX[')):
                    prehash = prehash.split(':', 1)[1]
                if(not prehash.startswith('$HEX[')):
                    sha1Hashlib = hashFunction(hashType, prehash.encode()).hexdigest()
                    if(hashed != sha1Hashlib):
                        prehash = prehash + " "
                        sha1Hashlib = hashFunction(hashType, prehash.encode()).hexdigest()

                        if(hashed != sha1Hashlib):
                            if(prehash.startswith(":")):
                                prehash = prehash.split(':', 1)[1]
                                sha1Hashlib = hashFunction(hashType, prehash.encode()).hexdigest()
                                if(hashed != sha1Hashlib):
                                    print("[1]->" + prehash + "<- hashlib:" + sha1Hashlib)
                                    continue
                                else:
                                    # All good
                                    pass
                            else:
                                print("[2]->" + prehash + "<- hashlib:" + sha1Hashlib)
                                continue
                        else:
                            # All good
                            pass
                    else:
                        # All good
                        pass
                else:
                    # All good
                    pass


                #print(hashed + ":" + prehash)
                insertData.append( UpdateOne({ "_id": hashed }, { "$set": { "password": prehash } }, upsert=True) )
                #print(line)


                i = i + 1
                if(i == 500000):
                    if(total >= 0): # 0:389500000  2:297000000 5:523000000
                        result = collection.bulk_write(insertData, ordered=False)
                        print(result.upserted_count)

                    total = total + i
                    print(firstChar + ":" + str(total))
                    i = 0
                    insertData = []

        result = collection.bulk_write(insertData, ordered=False)
        print(result.upserted_count)
