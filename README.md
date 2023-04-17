# Video Bill  (VSP Explorer)

VideoBill application presents the generated bill as a video.  It details each element of the billed information with the dynamically generated audio and video content.  In addition, using IBM Watson Trade Off Analytics and customer's historic usages, an optimal bill plan will be recommended to increase the customer satisfaction on the service provider by reducing cost and/or optimal features.


## Technologies
- Docker
- Apache webserver
- PHP, PHP Ming library [MING v0.4.8](https://github.com/libming/libming/archive/ming-0_4_8.zip)
- MySQL


## Docker

#### Prerequisite:
- Enable Virtualization in BIOS and check it in Windows by "Ctrl+Alt+Del -> Task Manager -> Performance tab"
- Install Hyper-V on Windows 10 [Hyper-V](https://docs.microsoft.com/en-us/virtualization/hyper-v-on-windows/quick-start/enable-hyper-v)
- Install Docker [IBM G2O LIUA](https://na.artifactory.swg-devops.com/artifactory/g2o-local-approved)

**Note**: Upon enabiling Hyper-V in Windows, VirtualBox machines will not run until Hyper-V disabled


#### Container management

To build and run the Video Bill program in local Docker continer, the commands can be used.

###### Build container
Download the content of this repository package and place in a new directory. Using command prompt, get into this directory and run the build command.
```
cd <Dockerfile location>
docker build -t ubuntu_lamp_loc .
```

###### Start container
This command executes the container in daemon mode and binds the host machine port 8080 to the webserver.
```
docker run -d -p 8080:80 --name ubuntu_lamp_loc ubuntu_lamp_loc
```

###### Status of container
To check the container execution status
```
docker ps
```

###### Logs of container
To see the logs of the container execution
```
docker logs ubuntu_lamp_loc
```

###### Stop container and clean memory
To stop the container from execution
```
docker stop ubuntu_lamp_loc
```

###### Remove unused Docker data
To remove unused containers, networks, dangling images, volumes from the system
```
docker system prune
```

###### Terminal
Using following command, the container machine can be accessed through the terminal window 
```
docker container exec -it ubuntu_lamp_loc bash
```

## Applications

- Video Bill application:  http://localhost:8080
- MySQL database administrator: http://localhost:8080/phpmyadmin
- MySQL
     - Database: db_vsp_explorer
     - Admin user: root/pw2mysql
     - Videobill user: vspexp/vsp1exp

All the Best.  :+1:
